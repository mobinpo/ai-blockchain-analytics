import { ref, computed, unref, withCtx, createVNode, createBlock, createCommentVNode, createTextVNode, toDisplayString, openBlock, Fragment, renderList, withDirectives, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderClass, ssrRenderList, ssrRenderStyle, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { Head, Link, router } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./AuthenticatedLayout-8TbwyeTu.js";
import "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const _sfc_main = {
  __name: "Trends",
  __ssrInlineRender: true,
  props: {
    timeframe: {
      type: String,
      default: "30d"
    },
    comparison: {
      type: String,
      default: "none"
    },
    trends: {
      type: Object,
      default: () => ({
        current_period: null,
        comparison_period: null,
        timeframe: "30d",
        comparison_type: "none"
      })
    }
  },
  setup(__props) {
    const props = __props;
    const selectedTimeframe = ref(props.timeframe);
    const sentimentChange = computed(() => {
      if (!props.trends.current_period || !props.trends.comparison_period) return null;
      return props.trends.current_period.overall_stats.average_sentiment - props.trends.comparison_period.overall_stats.average_sentiment;
    });
    const volumeChange = computed(() => {
      if (!props.trends.current_period || !props.trends.comparison_period) return null;
      return props.trends.current_period.overall_stats.total_posts - props.trends.comparison_period.overall_stats.total_posts;
    });
    const volumeChangePercentage = computed(() => {
      if (!props.trends.current_period || !props.trends.comparison_period) return 0;
      const prev = props.trends.comparison_period.overall_stats.total_posts;
      const curr = props.trends.current_period.overall_stats.total_posts;
      if (prev === 0) return 0;
      return Math.round((curr - prev) / prev * 100);
    });
    const expectedDays = computed(() => {
      const days = {
        "7d": 7,
        "30d": 30,
        "90d": 90,
        "180d": 180,
        "365d": 365
      };
      return days[props.timeframe] || 30;
    });
    const dataCoveragePercentage = computed(() => {
      if (!props.trends.current_period) return 0;
      const actual = props.trends.current_period.overall_stats.days_with_data;
      return Math.round(actual / expectedDays.value * 100);
    });
    const getSentimentColor = (sentiment) => {
      if (!sentiment) return "text-gray-500";
      if (sentiment > 0.2) return "text-green-600";
      if (sentiment < -0.2) return "text-red-600";
      return "text-gray-600";
    };
    const getChangeColor = (change) => {
      if (!change) return "text-gray-500";
      return change > 0 ? "text-green-600" : "text-red-600";
    };
    const getSentimentBarColor = (sentiment) => {
      if (!sentiment) return "bg-gray-400";
      if (sentiment > 0.2) return "bg-green-400 hover:bg-green-500";
      if (sentiment < -0.2) return "bg-red-400 hover:bg-red-500";
      return "bg-gray-400 hover:bg-gray-500";
    };
    const getSentimentBarWidth = (sentiment) => {
      return (sentiment + 1) / 2 * 100;
    };
    const getTimelineBarHeight = (sentiment) => {
      return (sentiment + 1) / 2 * 80 + 10;
    };
    const formatNumber = (num) => {
      if (!num) return "0";
      if (num >= 1e6) return (num / 1e6).toFixed(1) + "M";
      if (num >= 1e3) return (num / 1e3).toFixed(1) + "K";
      return num.toString();
    };
    const updateTimeframe = () => {
      router.get(route("sentiment-analysis.trends"), {
        timeframe: selectedTimeframe.value,
        comparison: "previous"
      });
    };
    const route = (name, params = {}) => {
      const routes = {
        "sentiment-analysis.index": "/sentiment-analysis",
        "sentiment-analysis.chart": "/sentiment-analysis/chart",
        "sentiment-analysis.platform": "/sentiment-analysis/platform",
        "sentiment-analysis.trends": "/sentiment-analysis/trends",
        "sentiment-analysis.correlations": "/sentiment-analysis/correlations"
      };
      let url = routes[name] || "#";
      if (Object.keys(params).length > 0) {
        const searchParams = new URLSearchParams(params);
        url += "?" + searchParams.toString();
      }
      return url;
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Sentiment Trends Analysis" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$1, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex justify-between items-center"${_scopeId}><div${_scopeId}><h2 class="font-semibold text-xl text-gray-800 leading-tight"${_scopeId}> Sentiment Trends Analysis </h2><p class="mt-1 text-sm text-gray-600"${_scopeId}> Long-term sentiment trends and historical patterns </p></div><div class="flex items-center space-x-3"${_scopeId}><select class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"${_scopeId}><option value="7d"${ssrIncludeBooleanAttr(Array.isArray(selectedTimeframe.value) ? ssrLooseContain(selectedTimeframe.value, "7d") : ssrLooseEqual(selectedTimeframe.value, "7d")) ? " selected" : ""}${_scopeId}>Last 7 Days</option><option value="30d"${ssrIncludeBooleanAttr(Array.isArray(selectedTimeframe.value) ? ssrLooseContain(selectedTimeframe.value, "30d") : ssrLooseEqual(selectedTimeframe.value, "30d")) ? " selected" : ""}${_scopeId}>Last 30 Days</option><option value="90d"${ssrIncludeBooleanAttr(Array.isArray(selectedTimeframe.value) ? ssrLooseContain(selectedTimeframe.value, "90d") : ssrLooseEqual(selectedTimeframe.value, "90d")) ? " selected" : ""}${_scopeId}>Last 90 Days</option><option value="180d"${ssrIncludeBooleanAttr(Array.isArray(selectedTimeframe.value) ? ssrLooseContain(selectedTimeframe.value, "180d") : ssrLooseEqual(selectedTimeframe.value, "180d")) ? " selected" : ""}${_scopeId}>Last 6 Months</option><option value="365d"${ssrIncludeBooleanAttr(Array.isArray(selectedTimeframe.value) ? ssrLooseContain(selectedTimeframe.value, "365d") : ssrLooseEqual(selectedTimeframe.value, "365d")) ? " selected" : ""}${_scopeId}>Last Year</option></select>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.index"),
              class: "inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` ← Dashboard `);
                } else {
                  return [
                    createTextVNode(" ← Dashboard ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></div>`);
          } else {
            return [
              createVNode("div", { class: "flex justify-between items-center" }, [
                createVNode("div", null, [
                  createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, " Sentiment Trends Analysis "),
                  createVNode("p", { class: "mt-1 text-sm text-gray-600" }, " Long-term sentiment trends and historical patterns ")
                ]),
                createVNode("div", { class: "flex items-center space-x-3" }, [
                  withDirectives(createVNode("select", {
                    "onUpdate:modelValue": ($event) => selectedTimeframe.value = $event,
                    onChange: updateTimeframe,
                    class: "rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  }, [
                    createVNode("option", { value: "7d" }, "Last 7 Days"),
                    createVNode("option", { value: "30d" }, "Last 30 Days"),
                    createVNode("option", { value: "90d" }, "Last 90 Days"),
                    createVNode("option", { value: "180d" }, "Last 6 Months"),
                    createVNode("option", { value: "365d" }, "Last Year")
                  ], 40, ["onUpdate:modelValue"]), [
                    [vModelSelect, selectedTimeframe.value]
                  ]),
                  createVNode(unref(Link), {
                    href: route("sentiment-analysis.index"),
                    class: "inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" ← Dashboard ")
                    ]),
                    _: 1
                  }, 8, ["href"])
                ])
              ])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r, _s, _t, _u, _v, _w, _x, _y, _z, _A, _B, _C, _D, _E, _F, _G, _H, _I, _J;
          if (_push2) {
            _push2(`<div class="py-12"${_scopeId}><div class="max-w-7xl mx-auto sm:px-6 lg:px-8"${_scopeId}><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}> Current Period Overview <span class="text-sm text-gray-500 ml-2"${_scopeId}> (${ssrInterpolate((_a = __props.trends.current_period) == null ? void 0 : _a.start_date)} to ${ssrInterpolate((_b = __props.trends.current_period) == null ? void 0 : _b.end_date)}) </span></h3><div class="grid grid-cols-1 md:grid-cols-3 gap-6"${_scopeId}><div class="text-center"${_scopeId}><div class="${ssrRenderClass([getSentimentColor((_d = (_c = __props.trends.current_period) == null ? void 0 : _c.overall_stats) == null ? void 0 : _d.average_sentiment), "text-3xl font-bold"])}"${_scopeId}>${ssrInterpolate(((_g = (_f = (_e = __props.trends.current_period) == null ? void 0 : _e.overall_stats) == null ? void 0 : _f.average_sentiment) == null ? void 0 : _g.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-500"${_scopeId}>Average Sentiment</div></div><div class="text-center"${_scopeId}><div class="text-3xl font-bold text-gray-900"${_scopeId}>${ssrInterpolate(formatNumber((_i = (_h = __props.trends.current_period) == null ? void 0 : _h.overall_stats) == null ? void 0 : _i.total_posts))}</div><div class="text-sm text-gray-500"${_scopeId}>Total Posts</div></div><div class="text-center"${_scopeId}><div class="text-3xl font-bold text-gray-900"${_scopeId}>${ssrInterpolate(((_k = (_j = __props.trends.current_period) == null ? void 0 : _j.overall_stats) == null ? void 0 : _k.days_with_data) || 0)}</div><div class="text-sm text-gray-500"${_scopeId}>Days with Data</div></div></div></div></div>`);
            if (__props.trends.comparison_period) {
              _push2(`<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}> Comparison with Previous Period </h3><div class="grid grid-cols-1 md:grid-cols-3 gap-6"${_scopeId}><div class="text-center"${_scopeId}><div class="text-lg text-gray-600 mb-2"${_scopeId}>Sentiment Change</div><div class="${ssrRenderClass([getChangeColor(sentimentChange.value), "text-2xl font-bold"])}"${_scopeId}>${ssrInterpolate(sentimentChange.value >= 0 ? "+" : "")}${ssrInterpolate(((_l = sentimentChange.value) == null ? void 0 : _l.toFixed(3)) || "N/A")}</div><div class="text-xs text-gray-500"${_scopeId}>${ssrInterpolate((_m = __props.trends.comparison_period.overall_stats.average_sentiment) == null ? void 0 : _m.toFixed(3))} → ${ssrInterpolate((_n = __props.trends.current_period.overall_stats.average_sentiment) == null ? void 0 : _n.toFixed(3))}</div></div><div class="text-center"${_scopeId}><div class="text-lg text-gray-600 mb-2"${_scopeId}>Volume Change</div><div class="${ssrRenderClass([getChangeColor(volumeChange.value), "text-2xl font-bold"])}"${_scopeId}>${ssrInterpolate(volumeChange.value >= 0 ? "+" : "")}${ssrInterpolate(volumeChangePercentage.value)}% </div><div class="text-xs text-gray-500"${_scopeId}>${ssrInterpolate(formatNumber(__props.trends.comparison_period.overall_stats.total_posts))} → ${ssrInterpolate(formatNumber(__props.trends.current_period.overall_stats.total_posts))}</div></div><div class="text-center"${_scopeId}><div class="text-lg text-gray-600 mb-2"${_scopeId}>Data Coverage</div><div class="text-2xl font-bold text-gray-900"${_scopeId}>${ssrInterpolate(dataCoveragePercentage.value)}% </div><div class="text-xs text-gray-500"${_scopeId}>${ssrInterpolate(__props.trends.current_period.overall_stats.days_with_data)} of ${ssrInterpolate(expectedDays.value)} days </div></div></div></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Platform Breakdown</h3><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"${_scopeId}><!--[-->`);
            ssrRenderList(((_o = __props.trends.current_period) == null ? void 0 : _o.platform_breakdown) || [], (platform) => {
              var _a2, _b2, _c2, _d2, _e2;
              _push2(`<div class="bg-gray-50 rounded-lg p-4"${_scopeId}><div class="flex justify-between items-center mb-3"${_scopeId}><h4 class="font-medium text-gray-900 capitalize"${_scopeId}>${ssrInterpolate(platform.platform)}</h4><span class="text-xs text-gray-500"${_scopeId}>${ssrInterpolate(formatNumber(platform.total_posts))} posts </span></div><div class="space-y-2"${_scopeId}><div class="flex justify-between"${_scopeId}><span class="text-sm text-gray-600"${_scopeId}>Avg Sentiment</span><span class="${ssrRenderClass([getSentimentColor(platform.average_sentiment), "text-sm font-medium"])}"${_scopeId}>${ssrInterpolate((_a2 = platform.average_sentiment) == null ? void 0 : _a2.toFixed(3))}</span></div><div class="flex justify-between"${_scopeId}><span class="text-sm text-gray-600"${_scopeId}>Range</span><span class="text-xs text-gray-500"${_scopeId}>${ssrInterpolate((_c2 = (_b2 = platform.sentiment_range) == null ? void 0 : _b2.min) == null ? void 0 : _c2.toFixed(2))} to ${ssrInterpolate((_e2 = (_d2 = platform.sentiment_range) == null ? void 0 : _d2.max) == null ? void 0 : _e2.toFixed(2))}</span></div></div><div class="mt-3 h-2 bg-gray-200 rounded-full"${_scopeId}><div class="${ssrRenderClass([getSentimentBarColor(platform.average_sentiment), "h-2 rounded-full transition-all duration-500"])}" style="${ssrRenderStyle({ width: getSentimentBarWidth(platform.average_sentiment) + "%" })}"${_scopeId}></div></div></div>`);
            });
            _push2(`<!--]--></div></div></div><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Daily Timeline</h3><div class="overflow-x-auto"${_scopeId}><div class="flex items-end space-x-1 h-32 min-w-full" style="${ssrRenderStyle({ "min-width": "800px" })}"${_scopeId}><!--[-->`);
            ssrRenderList(((_p = __props.trends.current_period) == null ? void 0 : _p.daily_timeline) || [], (day, index) => {
              var _a2;
              _push2(`<div class="${ssrRenderClass([getSentimentBarColor(day.sentiment), "flex-1 bg-gray-200 rounded-t relative group cursor-pointer"])}" style="${ssrRenderStyle({ height: getTimelineBarHeight(day.sentiment) + "%" })}"${_scopeId}><div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"${_scopeId}><div class="bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap"${_scopeId}><div${_scopeId}>${ssrInterpolate(new Date(day.date).toLocaleDateString())}</div><div${_scopeId}>${ssrInterpolate(day.platform)}</div><div${_scopeId}>Sentiment: ${ssrInterpolate((_a2 = day.sentiment) == null ? void 0 : _a2.toFixed(3))}</div><div${_scopeId}>Posts: ${ssrInterpolate(formatNumber(day.posts))}</div></div></div></div>`);
            });
            _push2(`<!--]--></div></div><div class="flex justify-between text-xs text-gray-500 mt-2"${_scopeId}><span${_scopeId}>${ssrInterpolate((_q = __props.trends.current_period) == null ? void 0 : _q.start_date)}</span><span${_scopeId}>${ssrInterpolate((_r = __props.trends.current_period) == null ? void 0 : _r.end_date)}</span></div></div></div><div class="bg-gray-50 rounded-lg p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-4"${_scopeId}>Explore Further</h3><div class="grid grid-cols-1 md:grid-cols-4 gap-4"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "twitter" }),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Twitter Analysis</div><div class="text-sm text-gray-500"${_scopeId2}>Platform-specific trends</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Twitter Analysis"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Platform-specific trends")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "reddit" }),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Reddit Analysis</div><div class="text-sm text-gray-500"${_scopeId2}>Community sentiment</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Reddit Analysis"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Community sentiment")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.chart"),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Price Correlation</div><div class="text-sm text-gray-500"${_scopeId2}>vs crypto prices</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Price Correlation"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "vs crypto prices")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.correlations"),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Multi-Coin Analysis</div><div class="text-sm text-gray-500"${_scopeId2}>Compare cryptocurrencies</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Multi-Coin Analysis"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Compare cryptocurrencies")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "max-w-7xl mx-auto sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, [
                        createTextVNode(" Current Period Overview "),
                        createVNode("span", { class: "text-sm text-gray-500 ml-2" }, " (" + toDisplayString((_s = __props.trends.current_period) == null ? void 0 : _s.start_date) + " to " + toDisplayString((_t = __props.trends.current_period) == null ? void 0 : _t.end_date) + ") ", 1)
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-3 gap-6" }, [
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", {
                            class: ["text-3xl font-bold", getSentimentColor((_v = (_u = __props.trends.current_period) == null ? void 0 : _u.overall_stats) == null ? void 0 : _v.average_sentiment)]
                          }, toDisplayString(((_y = (_x = (_w = __props.trends.current_period) == null ? void 0 : _w.overall_stats) == null ? void 0 : _x.average_sentiment) == null ? void 0 : _y.toFixed(3)) || "N/A"), 3),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Average Sentiment")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-3xl font-bold text-gray-900" }, toDisplayString(formatNumber((_A = (_z = __props.trends.current_period) == null ? void 0 : _z.overall_stats) == null ? void 0 : _A.total_posts)), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Total Posts")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-3xl font-bold text-gray-900" }, toDisplayString(((_C = (_B = __props.trends.current_period) == null ? void 0 : _B.overall_stats) == null ? void 0 : _C.days_with_data) || 0), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Days with Data")
                        ])
                      ])
                    ])
                  ]),
                  __props.trends.comparison_period ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"
                  }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, " Comparison with Previous Period "),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-3 gap-6" }, [
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-lg text-gray-600 mb-2" }, "Sentiment Change"),
                          createVNode("div", {
                            class: ["text-2xl font-bold", getChangeColor(sentimentChange.value)]
                          }, toDisplayString(sentimentChange.value >= 0 ? "+" : "") + toDisplayString(((_D = sentimentChange.value) == null ? void 0 : _D.toFixed(3)) || "N/A"), 3),
                          createVNode("div", { class: "text-xs text-gray-500" }, toDisplayString((_E = __props.trends.comparison_period.overall_stats.average_sentiment) == null ? void 0 : _E.toFixed(3)) + " → " + toDisplayString((_F = __props.trends.current_period.overall_stats.average_sentiment) == null ? void 0 : _F.toFixed(3)), 1)
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-lg text-gray-600 mb-2" }, "Volume Change"),
                          createVNode("div", {
                            class: ["text-2xl font-bold", getChangeColor(volumeChange.value)]
                          }, toDisplayString(volumeChange.value >= 0 ? "+" : "") + toDisplayString(volumeChangePercentage.value) + "% ", 3),
                          createVNode("div", { class: "text-xs text-gray-500" }, toDisplayString(formatNumber(__props.trends.comparison_period.overall_stats.total_posts)) + " → " + toDisplayString(formatNumber(__props.trends.current_period.overall_stats.total_posts)), 1)
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-lg text-gray-600 mb-2" }, "Data Coverage"),
                          createVNode("div", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(dataCoveragePercentage.value) + "% ", 1),
                          createVNode("div", { class: "text-xs text-gray-500" }, toDisplayString(__props.trends.current_period.overall_stats.days_with_data) + " of " + toDisplayString(expectedDays.value) + " days ", 1)
                        ])
                      ])
                    ])
                  ])) : createCommentVNode("", true),
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Platform Breakdown"),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" }, [
                        (openBlock(true), createBlock(Fragment, null, renderList(((_G = __props.trends.current_period) == null ? void 0 : _G.platform_breakdown) || [], (platform) => {
                          var _a2, _b2, _c2, _d2, _e2;
                          return openBlock(), createBlock("div", {
                            key: platform.platform,
                            class: "bg-gray-50 rounded-lg p-4"
                          }, [
                            createVNode("div", { class: "flex justify-between items-center mb-3" }, [
                              createVNode("h4", { class: "font-medium text-gray-900 capitalize" }, toDisplayString(platform.platform), 1),
                              createVNode("span", { class: "text-xs text-gray-500" }, toDisplayString(formatNumber(platform.total_posts)) + " posts ", 1)
                            ]),
                            createVNode("div", { class: "space-y-2" }, [
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-sm text-gray-600" }, "Avg Sentiment"),
                                createVNode("span", {
                                  class: ["text-sm font-medium", getSentimentColor(platform.average_sentiment)]
                                }, toDisplayString((_a2 = platform.average_sentiment) == null ? void 0 : _a2.toFixed(3)), 3)
                              ]),
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-sm text-gray-600" }, "Range"),
                                createVNode("span", { class: "text-xs text-gray-500" }, toDisplayString((_c2 = (_b2 = platform.sentiment_range) == null ? void 0 : _b2.min) == null ? void 0 : _c2.toFixed(2)) + " to " + toDisplayString((_e2 = (_d2 = platform.sentiment_range) == null ? void 0 : _d2.max) == null ? void 0 : _e2.toFixed(2)), 1)
                              ])
                            ]),
                            createVNode("div", { class: "mt-3 h-2 bg-gray-200 rounded-full" }, [
                              createVNode("div", {
                                class: ["h-2 rounded-full transition-all duration-500", getSentimentBarColor(platform.average_sentiment)],
                                style: { width: getSentimentBarWidth(platform.average_sentiment) + "%" }
                              }, null, 6)
                            ])
                          ]);
                        }), 128))
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Daily Timeline"),
                      createVNode("div", { class: "overflow-x-auto" }, [
                        createVNode("div", {
                          class: "flex items-end space-x-1 h-32 min-w-full",
                          style: { "min-width": "800px" }
                        }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(((_H = __props.trends.current_period) == null ? void 0 : _H.daily_timeline) || [], (day, index) => {
                            var _a2;
                            return openBlock(), createBlock("div", {
                              key: index,
                              class: ["flex-1 bg-gray-200 rounded-t relative group cursor-pointer", getSentimentBarColor(day.sentiment)],
                              style: { height: getTimelineBarHeight(day.sentiment) + "%" }
                            }, [
                              createVNode("div", { class: "absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10" }, [
                                createVNode("div", { class: "bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap" }, [
                                  createVNode("div", null, toDisplayString(new Date(day.date).toLocaleDateString()), 1),
                                  createVNode("div", null, toDisplayString(day.platform), 1),
                                  createVNode("div", null, "Sentiment: " + toDisplayString((_a2 = day.sentiment) == null ? void 0 : _a2.toFixed(3)), 1),
                                  createVNode("div", null, "Posts: " + toDisplayString(formatNumber(day.posts)), 1)
                                ])
                              ])
                            ], 6);
                          }), 128))
                        ])
                      ]),
                      createVNode("div", { class: "flex justify-between text-xs text-gray-500 mt-2" }, [
                        createVNode("span", null, toDisplayString((_I = __props.trends.current_period) == null ? void 0 : _I.start_date), 1),
                        createVNode("span", null, toDisplayString((_J = __props.trends.current_period) == null ? void 0 : _J.end_date), 1)
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-gray-50 rounded-lg p-6" }, [
                    createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-4" }, "Explore Further"),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-4 gap-4" }, [
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.platform", { platform: "twitter" }),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Twitter Analysis"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Platform-specific trends")
                        ]),
                        _: 1
                      }, 8, ["href"]),
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.platform", { platform: "reddit" }),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Reddit Analysis"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Community sentiment")
                        ]),
                        _: 1
                      }, 8, ["href"]),
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.chart"),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Price Correlation"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "vs crypto prices")
                        ]),
                        _: 1
                      }, 8, ["href"]),
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.correlations"),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Multi-Coin Analysis"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Compare cryptocurrencies")
                        ]),
                        _: 1
                      }, 8, ["href"])
                    ])
                  ])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/SentimentAnalysis/Trends.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
