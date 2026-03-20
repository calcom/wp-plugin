(function (window) {
    
    // prevent double initialization
    if (window.Cal && window.Cal.loaded) return;

    var doc = window.document;

    window.Cal = function () {
        var cal = window.Cal;
        var args = arguments;

        cal.q = cal.q || [];
        cal.ns = cal.ns || {};

        // load embed.js only once
        if (!cal.loaded) {
            var script = doc.createElement("script");
            script.src = window.calcomScriptUrl || "https://cal.com/embed.js";
            doc.head.appendChild(script);
            cal.loaded = true;
        }

        // namespace handling
        if (args[0] === "init" && typeof args[1] === "string") {
            var namespace = args[1];

            if (!cal.ns[namespace]) {
                var api = function () {
                    api.q.push(arguments);
                };
                api.q = [];
                cal.ns[namespace] = api;
            }

            cal.ns[namespace].q.push(args);
            return cal.ns[namespace];
        }

        // default queue
        cal.q.push(args);
    };
})(window);