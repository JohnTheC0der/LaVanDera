/**
 * LVLinks: rewrites links to pages that are already converted from "Page.html" to "Page.php".
 * LV.renderNav() (from lavandera_demo.js) still builds its menu with the old .html names, so each converted
 * page calls LVLinks.fix() right after renderNav().  When you convert another page, add its name to CONVERTED.
 */
const LVLinks = (function () {
    const CONVERTED = [
        'Customer_Home_Page', 'Customer_Stores_Page', 'Customer_View_Store_Page', 'Customer_Place_Order_Page',
        'Customer_Confirm_Order', 'Customer_My_Orders_Page', 'Customer_Profile_Page',
        'lavandera_rider_dashboard', 'Rider_Orders_Page', 'Rider_Order_History_Page', 'Rider_Profile_Page'
    ];

    function fix(root) {
        // Look at every <a href="..."> and swap .html for .php when the page name is in CONVERTED.
        (root || document).querySelectorAll('a[href]').forEach(function (link) {
            const match = link.getAttribute('href').match(/^(.*?)([A-Za-z_]+)\.html((?:[?#].*)?)$/);
            if (match && CONVERTED.indexOf(match[2]) > -1) {
                link.setAttribute('href', match[1] + match[2] + '.php' + match[3]);
            }
        });
    }
    return { fix: fix };
})();
