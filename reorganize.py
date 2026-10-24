#!/usr/bin/env python3
"""
LaVanDera: reorganize the flat project folder into  public/  src/  database/
and automatically fix the links between files.

Run from the ROOT of the repo (the folder that contains index.html):

    python reorganize.py            # DRY RUN: shows what would happen, changes nothing
    python reorganize.py --apply    # does it (uses `git mv` so history is kept)

Needs only Python 3. Safe to re-run: files already moved are left alone.
"""
import argparse
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

IMAGE_EXT = {".png", ".jpg", ".jpeg", ".gif", ".svg", ".avif", ".webp", ".ico", ".bmp"}
TEXT_EXT = {".html", ".htm", ".css", ".js", ".php"}      # files whose links get rewritten
LINKABLE_EXT = TEXT_EXT | IMAGE_EXT
LEAVE_ALONE = {".git", ".vscode", "node_modules", "README.md", "readme.md", ".gitignore",
               ".gitattributes", "reorganize.py", "package.json", "package-lock.json",
               "LICENSE", ".env", "public", "src", "database"}

AUTH_WORDS = ("log_in", "login", "sign_up", "signup", "become_partner", "partner_thanks",
              "wait", "pending")


def classify(name):
    """Return the destination folder (relative to repo root) for a root-level file."""
    low, ext = name.lower(), Path(name).suffix.lower()
    if ext in IMAGE_EXT:
        return "public/images"
    if ext == ".css":
        return "public/css"
    if ext == ".js":
        return "public/js"
    if ext == ".sql":
        return "database"
    if ext in (".html", ".htm"):
        if low == "index.html" or low.startswith("home_screen"):
            return "public"
        if any(w in low for w in AUTH_WORDS):
            return "public/auth"
        if low.startswith("admin"):
            return "public/admin"
        if low.startswith("store"):
            return "public/store"
        if low.startswith("customer"):
            return "public/customer"
        if "rider" in low:
            return "public/rider"
        return "public"          # unclassified: lands in public/ and is reported
    return None                  # unknown file type: left where it is


# A reference = an opening quote or "(" , optional ./ or /, a bare filename, optional ?query/#hash,
# then a closing quote or ")".  Catches href="", src="", url(), and 'page.html' inside JS.
REF = re.compile(
    r"""(?P<q>["'(])(?P<pre>\./|/)?(?P<name>[^\s"'()<>/\\?#:]+\.[A-Za-z0-9]+)"""
    r"""(?P<suf>[?#][^\s"'()<>]*)?(?=["')])"""
)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true", help="actually move files and edit links")
    args = ap.parse_args()
    root = Path.cwd()
    use_git = (root / ".git").exists()

    # ---- 1. plan the moves --------------------------------------------------
    moves = {}                    # old name -> new relative path (posix)
    skipped = []
    for entry in sorted(root.iterdir()):
        if entry.is_dir() or entry.name in LEAVE_ALONE:
            continue
        dest = classify(entry.name)
        if dest is None:
            skipped.append(entry.name)
            continue
        moves[entry.name] = f"{dest}/{entry.name}"
    by_lower = {k.lower(): (k, v) for k, v in moves.items()}

    print("PLANNED MOVES")
    for old, new in moves.items():
        print(f"  {old:<42} -> {new}")
    hidden_db = root / ".database"
    if hidden_db.is_dir():
        print(f"  {'.database/':<42} -> database/")
    for s in skipped:
        print(f"  (left in place, unknown type) {s}")
    other_dirs = [d.name for d in root.iterdir()
                  if d.is_dir() and d.name not in LEAVE_ALONE and d.name != ".database"]
    for d in other_dirs:
        print(f"  (folder left in place, move by hand if needed) {d}/")

    # ---- 2. do the moves ----------------------------------------------------
    def move(src, dst):
        dst.parent.mkdir(parents=True, exist_ok=True)
        if use_git:
            r = subprocess.run(["git", "mv", str(src), str(dst)], capture_output=True, text=True)
            if r.returncode == 0:
                return
            # file not tracked yet -> plain move
        shutil.move(str(src), str(dst))

    if args.apply:
        for old, new in moves.items():
            move(root / old, root / new)
        if hidden_db.is_dir():
            target = root / "database"
            if not target.exists():
                move(hidden_db, target)
            else:
                for child in hidden_db.iterdir():
                    move(child, target / child.name)
                try:
                    hidden_db.rmdir()
                except OSError:
                    pass
        (root / "public" / "uploads").mkdir(parents=True, exist_ok=True)
        (root / "src").mkdir(exist_ok=True)
        for keep in (root / "public" / "uploads" / ".gitkeep", root / "src" / ".gitkeep"):
            keep.touch()
        gi = root / ".gitignore"
        wanted = [".env", "node_modules/", ".DS_Store", "Thumbs.db",
                  "public/uploads/*", "!public/uploads/.gitkeep"]
        have = gi.read_text().splitlines() if gi.exists() else []
        add = [w for w in wanted if w not in have]
        if add:
            with open(gi, "a", newline="") as f:
                if have and not gi.read_text().endswith("\n"):
                    f.write("\n")
                f.write("\n".join(add) + "\n")

    # ---- 3. rewrite links ---------------------------------------------------
    print("\nLINK FIXES")
    unresolved = {}
    total = 0
    for old, new in moves.items():
        if Path(old).suffix.lower() not in TEXT_EXT:
            continue
        new_dir = Path(new).parent
        read_path = root / (new if args.apply else old)
        try:
            raw = read_path.read_bytes().decode("utf-8")
        except UnicodeDecodeError:
            print(f"  ! could not read {old} as UTF-8, fix its links by hand")
            continue
        count = 0

        def fix(m):
            nonlocal count
            hit = by_lower.get(m["name"].lower())
            if hit is None:
                if Path(m["name"]).suffix.lower() in LINKABLE_EXT and m["pre"] != "/":
                    unresolved.setdefault(old, set()).add(m["name"])
                return m.group(0)
            target = hit[1]
            if m["pre"] == "/":      # site-root link: public/ is the web root
                rel = "/" + os.path.relpath(target, "public").replace(os.sep, "/")
            else:
                rel = os.path.relpath(target, new_dir).replace(os.sep, "/")
            count += 1
            return f'{m["q"]}{rel}{m["suf"] or ""}'

        out = REF.sub(fix, raw)
        if out != raw:
            total += count
            print(f"  {new}: {count} link(s) updated")
            if args.apply:
                read_path.write_bytes(out.encode("utf-8"))

    print(f"\n{total} link(s) {'updated' if args.apply else 'would be updated'}.")
    if unresolved:
        print("\nREFERENCES TO FILES THAT DON'T EXIST YET (not changed; check these):")
        for f, names in unresolved.items():
            print(f"  {f}: {', '.join(sorted(names))}")
    if not args.apply:
        print("\nDry run only. Re-run with  --apply  to do it.")


if __name__ == "__main__":
    sys.exit(main())
