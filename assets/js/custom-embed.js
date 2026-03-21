(function () {
  document.addEventListener("DOMContentLoaded", function () {

    if (!window.calCustomData || typeof window.calCustomData !== "object") return;

    var data = window.calCustomData;

    if (!data || !data.calLink) return;

    data.ui = (data.ui && typeof data.ui === "object" && !Array.isArray(data.ui)) ? data.ui : {};
    data.config = (data.config && typeof data.config === "object" && !Array.isArray(data.config)) ? data.config : {};

    var scriptUrl = data.customCalInstance
      ? data.customCalInstance.replace(/\/$/, "") + "/embed/embed.js"
      : "https://cal.com/embed.js";

    window.calcomScriptUrl = scriptUrl;

    // unique namespace for this embed
    var ns = "cal_" + data.elementId;
    var api = Cal("init", ns, data.customCalInstance ? { origin: data.customCalInstance } : {});

    var config = Object.assign({ calLink: data.calLink }, data.config);

    if (Object.keys(data.ui).length) {
      api("ui", data.ui);
    }

    if (data.type === 1) {
      config.elementOrSelector = "#" + data.elementId;
      api("inline", config);
    }

    if (data.type === 2) {
      var el = document.getElementById(data.elementId);
      if (el) el.addEventListener("click", function () { api("modal", config); });
    }

    if (data.type === 3) {
      api("floatingButton", config);
    }
  });
})();