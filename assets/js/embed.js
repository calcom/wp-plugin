(function () {
    document.addEventListener("DOMContentLoaded", function () {

        if (typeof window.calcomData === "undefined") return;

        var data = window.calcomData;

        // determine embed script URL (self-hosted or default)
        var scriptUrl = data.customCalUrl
            ? data.customCalUrl + '/embed/embed.js'
            : 'https://cal.com/embed.js';

        // initialize Cal.com loader
        (function (C, A, L) {

            var pushArgs = function (obj, args) { obj.q.push(args); };
            var doc = C.document;

            C.Cal = C.Cal || function () {

                var cal = C.Cal;
                var args = arguments;

                if (!cal.loaded) {

                    cal.ns = {};
                    cal.q = cal.q || [];

                    var script = doc.createElement("script");
                    script.src = A;

                    doc.head.appendChild(script);
                    cal.loaded = true;
                }

                if (args[0] === L) {

                    var api = function () { pushArgs(api, arguments); };
                    var namespace = args[1];

                    api.q = api.q || [];

                    if (typeof namespace === "string") {

                        cal.ns[namespace] = api;
                        pushArgs(api, args);
                    } else {
                        pushArgs(cal, args);
                    }

                    return;
                }

                pushArgs(cal, args);
            };
        })(window, scriptUrl, "init");

        // initialize Cal with self-host origin if needed
        if (data.customCalUrl) {
            Cal("init", { origin: data.customCalUrl });
        } else {
            Cal("init");
        }

        // handle inline embed
        if (data.type === 1) {

            var inlineConfig = data.config || {};

            // ensure the target element exists for inline embeds
            if (!inlineConfig.elementOrSelector) {
                inlineConfig.elementOrSelector = "#calcom-embed";
            }

            Cal("inline", inlineConfig);
        }

        // handle floating button embed
        if (data.type === 3) {
            Cal("floatingButton", data.config);
        }

        // modal trigger
        if (data.type === 2) {
            
            var el = document.getElementById("calcom-embed-link");
            
            if (el) {
                el.addEventListener("click", function () {
                    Cal("modal", data.config);
                });
            }
        }
    });
})();