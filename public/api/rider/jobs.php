<?php
/**
 * GET  -> {available, current, history, stats} for the signed-in rider.
 *          Optional ?history_limit=20&history_offset=0 pages through finished jobs (stats.completed = total).
 * POST -> JSON {"action": "accept", "job_id": 12}; returns the refreshed lists.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
require_once LV_ROOT . '/src/rider/rider_repo.php';

api_run(function () {
    $user = require_rider_api();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $in = json_input();
        if (!isset($in['action']) || $in['action'] !== 'accept') {
            throw new UserError('Unknown action.');
        }
        rider_accept_job($user['id'], (int) (isset($in['job_id']) ? $in['job_id'] : 0));
    }
    json_out(['ok' => true] + rider_board(
        $user['id'],
        isset($_GET['history_limit']) ? (int) $_GET['history_limit'] : 50,
        isset($_GET['history_offset']) ? (int) $_GET['history_offset'] : 0
    ));
});
