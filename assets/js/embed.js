(function () {
    document.addEventListener("DOMContentLoaded", function () {
        if (!window.calcomData || typeof window.calcomData !== "object") return;

        var data = window.calcomData;
        if (!data) return;

        data.ui = (data.ui && typeof data.ui === "object" && !Array.isArray(data.ui)) ? data.ui : {};
        data.config = (data.config && typeof data.config === "object" && !Array.isArray(data.config)) ? data.config : {};

        var scriptUrl = data.customCalInstance
            ? data.customCalInstance.replace(/\/$/, "") + "/embed/embed.js"
            : "https://cal.com/embed.js";

        window.calcomScriptUrl = scriptUrl;

        var api = Cal("init", "cal_core");

        var config = Object.assign({ calLink: data.url || "" }, data.config);

        if (Object.keys(data.ui).length) {
            api("ui", data.ui);
        }

        // inline embed
        if (data.type === 1) {
            config.elementOrSelector = "#calcom-embed";
            api("inline", config);
        }

        // modal
        if (data.type === 2) {
            var el = document.getElementById("calcom-embed-link");
            if (el) el.addEventListener("click", function () { api("modal", config); });
        }

        // floating button
        if (data.type === 3) {
            api("floatingButton", config);
        }
    });
})();