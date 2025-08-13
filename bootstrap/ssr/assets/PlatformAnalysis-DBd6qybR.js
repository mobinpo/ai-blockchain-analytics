import { computed, unref, withCtx, createVNode, toDisplayString, createBlock, openBlock, Fragment, renderList, createTextVNode, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderClass, ssrInterpolate, ssrRenderStyle, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { Head, Link } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./AuthenticatedLayout-8TbwyeTu.js";
import "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const _sfc_main = {
  __name: "PlatformAnalysis",
  __ssrInlineRender: true,
  props: {
    platform: {
      type: String,
      default: "all"
    },
    category: {
      type: String,
      default: "all"
    },
    days: {
      type: Number,
      default: 30
    },
    analysis: {
      type: Object,
      default: () => ({
        summary: {
          total_days: 0,
          total_posts: 0,
          average_sentiment: 0,
          sentiment_range: { min: 0, max: 0 },
          average_volatility: 0
        },
        daily_breakdown: [],
        sentiment_distribution: {
          very_positive: 0,
          positive: 0,
          neutral: 0,
          negative: 0,
          very_negative: 0
        }
      })
    }
  },
  setup(__props) {
    const props = __props;
    const totalSentimentPosts = computed(() => {
      const dist = props.analysis.sentiment_distribution;
      return dist.very_positive + dist.positive + dist.neutral + dist.negative + dist.very_negative;
    });
    const getSentimentColor = (sentiment) => {
      if (!sentiment) return "text-gray-500";
      if (sentiment > 0.2) return "text-green-600";
      if (sentiment < -0.2) return "text-red-600";
      return "text-gray-600";
    };
    const formatNumber = (num) => {
      if (!num) return "0";
      if (num >= 1e6) return (num / 1e6).toFixed(1) + "M";
      if (num >= 1e3) return (num / 1e3).toFixed(1) + "K";
      return num.toString();
    };
    const getPercentage = (value) => {
      if (!value || totalSentimentPosts.value === 0) return 0;
      return value / totalSentimentPosts.value * 100;
    };
    const route = (name, params = {}) => {
      const routes = {
        "sentiment-analysis.index": "/sentiment-analysis",
        "sentiment-analysis.chart": "/sentiment-analysis/chart",
        "sentiment-analysis.platform": "/sentiment-analysis/platform"
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
      _push(ssrRenderComponent(unref(Head), {
        title: `${__props.platform.charAt(0).toUpperCase() + __props.platform.slice(1)} Sentiment Analysis`
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$1, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex justify-between items-center"${_scopeId}><div${_scopeId}><h2 class="font-semibold text-xl text-gray-800 leading-tight"${_scopeId}>${ssrInterpolate(__props.platform.charAt(0).toUpperCase() + __props.platform.slice(1))} Sentiment Analysis `);
            if (__props.category !== "all") {
              _push2(`<span class="text-sm text-gray-500 ml-2"${_scopeId}> (${ssrInterpolate(__props.category.charAt(0).toUpperCase() + __props.category.slice(1))}) </span>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</h2><p class="mt-1 text-sm text-gray-600"${_scopeId}> Deep dive into platform-specific sentiment trends and patterns </p></div><div class="flex items-center space-x-3"${_scopeId}>`);
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
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.chart"),
              class: "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` View Charts `);
                } else {
                  return [
                    createTextVNode(" View Charts ")
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
                  createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, [
                    createTextVNode(toDisplayString(__props.platform.charAt(0).toUpperCase() + __props.platform.slice(1)) + " Sentiment Analysis ", 1),
                    __props.category !== "all" ? (openBlock(), createBlock("span", {
                      key: 0,
                      class: "text-sm text-gray-500 ml-2"
                    }, " (" + toDisplayString(__props.category.charAt(0).toUpperCase() + __props.category.slice(1)) + ") ", 1)) : createCommentVNode("", true)
                  ]),
                  createVNode("p", { class: "mt-1 text-sm text-gray-600" }, " Deep dive into platform-specific sentiment trends and patterns ")
                ]),
                createVNode("div", { class: "flex items-center space-x-3" }, [
                  createVNode(unref(Link), {
                    href: route("sentiment-analysis.index"),
                    class: "inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" ← Dashboard ")
                    ]),
                    _: 1
                  }, 8, ["href"]),
                  createVNode(unref(Link), {
                    href: route("sentiment-analysis.chart"),
                    class: "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" View Charts ")
                    ]),
                    _: 1
                  }, 8, ["href"])
                ])
              ])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j;
          if (_push2) {
            _push2(`<div class="py-12"${_scopeId}><div class="max-w-7xl mx-auto sm:px-6 lg:px-8"${_scopeId}><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Summary Statistics</h3><div class="grid grid-cols-1 md:grid-cols-5 gap-6"${_scopeId}><div class="text-center"${_scopeId}><div class="${ssrRenderClass([getSentimentColor(__props.analysis.summary.average_sentiment), "text-2xl font-bold"])}"${_scopeId}>${ssrInterpolate(((_a = __props.analysis.summary.average_sentiment) == null ? void 0 : _a.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-500"${_scopeId}>Average Sentiment</div></div><div class="text-center"${_scopeId}><div class="text-2xl font-bold text-gray-900"${_scopeId}>${ssrInterpolate(formatNumber(__props.analysis.summary.total_posts))}</div><div class="text-sm text-gray-500"${_scopeId}>Total Posts</div></div><div class="text-center"${_scopeId}><div class="text-2xl font-bold text-gray-900"${_scopeId}>${ssrInterpolate(__props.analysis.summary.total_days)}</div><div class="text-sm text-gray-500"${_scopeId}>Days Analyzed</div></div><div class="text-center"${_scopeId}><div class="text-2xl font-bold text-gray-600"${_scopeId}>${ssrInterpolate(((_c = (_b = __props.analysis.summary.sentiment_range) == null ? void 0 : _b.min) == null ? void 0 : _c.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-500"${_scopeId}>Min Sentiment</div></div><div class="text-center"${_scopeId}><div class="text-2xl font-bold text-gray-600"${_scopeId}>${ssrInterpolate(((_e = (_d = __props.analysis.summary.sentiment_range) == null ? void 0 : _d.max) == null ? void 0 : _e.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-500"${_scopeId}>Max Sentiment</div></div></div></div></div><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Sentiment Distribution</h3><div class="space-y-4"${_scopeId}><div class="flex items-center"${_scopeId}><div class="w-24 text-sm text-gray-600"${_scopeId}>Very Positive</div><div class="flex-1 mx-4"${_scopeId}><div class="bg-gray-200 rounded-full h-4 relative"${_scopeId}><div class="bg-green-500 h-4 rounded-full transition-all duration-500" style="${ssrRenderStyle({ width: getPercentage(__props.analysis.sentiment_distribution.very_positive) + "%" })}"${_scopeId}></div></div></div><div class="w-16 text-sm text-gray-900 text-right"${_scopeId}>${ssrInterpolate(formatNumber(__props.analysis.sentiment_distribution.very_positive))}</div></div><div class="flex items-center"${_scopeId}><div class="w-24 text-sm text-gray-600"${_scopeId}>Positive</div><div class="flex-1 mx-4"${_scopeId}><div class="bg-gray-200 rounded-full h-4 relative"${_scopeId}><div class="bg-green-400 h-4 rounded-full transition-all duration-500" style="${ssrRenderStyle({ width: getPercentage(__props.analysis.sentiment_distribution.positive) + "%" })}"${_scopeId}></div></div></div><div class="w-16 text-sm text-gray-900 text-right"${_scopeId}>${ssrInterpolate(formatNumber(__props.analysis.sentiment_distribution.positive))}</div></div><div class="flex items-center"${_scopeId}><div class="w-24 text-sm text-gray-600"${_scopeId}>Neutral</div><div class="flex-1 mx-4"${_scopeId}><div class="bg-gray-200 rounded-full h-4 relative"${_scopeId}><div class="bg-gray-400 h-4 rounded-full transition-all duration-500" style="${ssrRenderStyle({ width: getPercentage(__props.analysis.sentiment_distribution.neutral) + "%" })}"${_scopeId}></div></div></div><div class="w-16 text-sm text-gray-900 text-right"${_scopeId}>${ssrInterpolate(formatNumber(__props.analysis.sentiment_distribution.neutral))}</div></div><div class="flex items-center"${_scopeId}><div class="w-24 text-sm text-gray-600"${_scopeId}>Negative</div><div class="flex-1 mx-4"${_scopeId}><div class="bg-gray-200 rounded-full h-4 relative"${_scopeId}><div class="bg-red-400 h-4 rounded-full transition-all duration-500" style="${ssrRenderStyle({ width: getPercentage(__props.analysis.sentiment_distribution.negative) + "%" })}"${_scopeId}></div></div></div><div class="w-16 text-sm text-gray-900 text-right"${_scopeId}>${ssrInterpolate(formatNumber(__props.analysis.sentiment_distribution.negative))}</div></div><div class="flex items-center"${_scopeId}><div class="w-24 text-sm text-gray-600"${_scopeId}>Very Negative</div><div class="flex-1 mx-4"${_scopeId}><div class="bg-gray-200 rounded-full h-4 relative"${_scopeId}><div class="bg-red-500 h-4 rounded-full transition-all duration-500" style="${ssrRenderStyle({ width: getPercentage(__props.analysis.sentiment_distribution.very_negative) + "%" })}"${_scopeId}></div></div></div><div class="w-16 text-sm text-gray-900 text-right"${_scopeId}>${ssrInterpolate(formatNumber(__props.analysis.sentiment_distribution.very_negative))}</div></div></div></div></div><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Daily Breakdown</h3><div class="overflow-x-auto"${_scopeId}><table class="min-w-full divide-y divide-gray-200"${_scopeId}><thead class="bg-gray-50"${_scopeId}><tr${_scopeId}><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Date </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Sentiment </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Posts </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Volatility </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Distribution </th></tr></thead><tbody class="bg-white divide-y divide-gray-200"${_scopeId}><!--[-->`);
            ssrRenderList(__props.analysis.daily_breakdown, (day) => {
              _push2(`<tr${_scopeId}><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"${_scopeId}>${ssrInterpolate(new Date(day.date).toLocaleDateString())}</td><td class="${ssrRenderClass([getSentimentColor(day.sentiment), "px-6 py-4 whitespace-nowrap text-sm"])}"${_scopeId}>${ssrInterpolate(day.sentiment.toFixed(3))}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"${_scopeId}>${ssrInterpolate(formatNumber(day.posts))}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"${_scopeId}>${ssrInterpolate(day.volatility.toFixed(3))}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"${_scopeId}><div class="flex space-x-1"${_scopeId}><div class="w-3 h-3 bg-green-500 rounded-full"${ssrRenderAttr("title", `Very Positive: ${day.distribution.very_positive}`)}${_scopeId}></div><div class="w-3 h-3 bg-green-400 rounded-full"${ssrRenderAttr("title", `Positive: ${day.distribution.positive}`)}${_scopeId}></div><div class="w-3 h-3 bg-gray-400 rounded-full"${ssrRenderAttr("title", `Neutral: ${day.distribution.neutral}`)}${_scopeId}></div><div class="w-3 h-3 bg-red-400 rounded-full"${ssrRenderAttr("title", `Negative: ${day.distribution.negative}`)}${_scopeId}></div><div class="w-3 h-3 bg-red-500 rounded-full"${ssrRenderAttr("title", `Very Negative: ${day.distribution.very_negative}`)}${_scopeId}></div></div></td></tr>`);
            });
            _push2(`<!--]--></tbody></table></div></div></div><div class="bg-gray-50 rounded-lg p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-4"${_scopeId}>Explore Different Views</h3><div class="grid grid-cols-1 md:grid-cols-3 gap-4"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: __props.platform, category: __props.category, days: 7 }),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Last 7 Days</div><div class="text-sm text-gray-500"${_scopeId2}>Short-term trends</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Last 7 Days"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Short-term trends")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: __props.platform, category: __props.category, days: 90 }),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Last 90 Days</div><div class="text-sm text-gray-500"${_scopeId2}>Long-term analysis</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Last 90 Days"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Long-term analysis")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.chart", { platform: __props.platform, category: __props.category }),
              class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium"${_scopeId2}>Price Correlation</div><div class="text-sm text-gray-500"${_scopeId2}>vs cryptocurrency prices</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium" }, "Price Correlation"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "vs cryptocurrency prices")
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
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Summary Statistics"),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-5 gap-6" }, [
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", {
                            class: ["text-2xl font-bold", getSentimentColor(__props.analysis.summary.average_sentiment)]
                          }, toDisplayString(((_f = __props.analysis.summary.average_sentiment) == null ? void 0 : _f.toFixed(3)) || "N/A"), 3),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Average Sentiment")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(formatNumber(__props.analysis.summary.total_posts)), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Total Posts")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(__props.analysis.summary.total_days), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Days Analyzed")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-2xl font-bold text-gray-600" }, toDisplayString(((_h = (_g = __props.analysis.summary.sentiment_range) == null ? void 0 : _g.min) == null ? void 0 : _h.toFixed(3)) || "N/A"), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Min Sentiment")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-2xl font-bold text-gray-600" }, toDisplayString(((_j = (_i = __props.analysis.summary.sentiment_range) == null ? void 0 : _i.max) == null ? void 0 : _j.toFixed(3)) || "N/A"), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Max Sentiment")
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Sentiment Distribution"),
                      createVNode("div", { class: "space-y-4" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "w-24 text-sm text-gray-600" }, "Very Positive"),
                          createVNode("div", { class: "flex-1 mx-4" }, [
                            createVNode("div", { class: "bg-gray-200 rounded-full h-4 relative" }, [
                              createVNode("div", {
                                class: "bg-green-500 h-4 rounded-full transition-all duration-500",
                                style: { width: getPercentage(__props.analysis.sentiment_distribution.very_positive) + "%" }
                              }, null, 4)
                            ])
                          ]),
                          createVNode("div", { class: "w-16 text-sm text-gray-900 text-right" }, toDisplayString(formatNumber(__props.analysis.sentiment_distribution.very_positive)), 1)
                        ]),
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "w-24 text-sm text-gray-600" }, "Positive"),
                          createVNode("div", { class: "flex-1 mx-4" }, [
                            createVNode("div", { class: "bg-gray-200 rounded-full h-4 relative" }, [
                              createVNode("div", {
                                class: "bg-green-400 h-4 rounded-full transition-all duration-500",
                                style: { width: getPercentage(__props.analysis.sentiment_distribution.positive) + "%" }
                              }, null, 4)
                            ])
                          ]),
                          createVNode("div", { class: "w-16 text-sm text-gray-900 text-right" }, toDisplayString(formatNumber(__props.analysis.sentiment_distribution.positive)), 1)
                        ]),
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "w-24 text-sm text-gray-600" }, "Neutral"),
                          createVNode("div", { class: "flex-1 mx-4" }, [
                            createVNode("div", { class: "bg-gray-200 rounded-full h-4 relative" }, [
                              createVNode("div", {
                                class: "bg-gray-400 h-4 rounded-full transition-all duration-500",
                                style: { width: getPercentage(__props.analysis.sentiment_distribution.neutral) + "%" }
                              }, null, 4)
                            ])
                          ]),
                          createVNode("div", { class: "w-16 text-sm text-gray-900 text-right" }, toDisplayString(formatNumber(__props.analysis.sentiment_distribution.neutral)), 1)
                        ]),
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "w-24 text-sm text-gray-600" }, "Negative"),
                          createVNode("div", { class: "flex-1 mx-4" }, [
                            createVNode("div", { class: "bg-gray-200 rounded-full h-4 relative" }, [
                              createVNode("div", {
                                class: "bg-red-400 h-4 rounded-full transition-all duration-500",
                                style: { width: getPercentage(__props.analysis.sentiment_distribution.negative) + "%" }
                              }, null, 4)
                            ])
                          ]),
                          createVNode("div", { class: "w-16 text-sm text-gray-900 text-right" }, toDisplayString(formatNumber(__props.analysis.sentiment_distribution.negative)), 1)
                        ]),
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "w-24 text-sm text-gray-600" }, "Very Negative"),
                          createVNode("div", { class: "flex-1 mx-4" }, [
                            createVNode("div", { class: "bg-gray-200 rounded-full h-4 relative" }, [
                              createVNode("div", {
                                class: "bg-red-500 h-4 rounded-full transition-all duration-500",
                                style: { width: getPercentage(__props.analysis.sentiment_distribution.very_negative) + "%" }
                              }, null, 4)
                            ])
                          ]),
                          createVNode("div", { class: "w-16 text-sm text-gray-900 text-right" }, toDisplayString(formatNumber(__props.analysis.sentiment_distribution.very_negative)), 1)
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Daily Breakdown"),
                      createVNode("div", { class: "overflow-x-auto" }, [
                        createVNode("table", { class: "min-w-full divide-y divide-gray-200" }, [
                          createVNode("thead", { class: "bg-gray-50" }, [
                            createVNode("tr", null, [
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Date "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Sentiment "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Posts "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Volatility "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Distribution ")
                            ])
                          ]),
                          createVNode("tbody", { class: "bg-white divide-y divide-gray-200" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.analysis.daily_breakdown, (day) => {
                              return openBlock(), createBlock("tr", {
                                key: day.date
                              }, [
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900" }, toDisplayString(new Date(day.date).toLocaleDateString()), 1),
                                createVNode("td", {
                                  class: ["px-6 py-4 whitespace-nowrap text-sm", getSentimentColor(day.sentiment)]
                                }, toDisplayString(day.sentiment.toFixed(3)), 3),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900" }, toDisplayString(formatNumber(day.posts)), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-600" }, toDisplayString(day.volatility.toFixed(3)), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-500" }, [
                                  createVNode("div", { class: "flex space-x-1" }, [
                                    createVNode("div", {
                                      class: "w-3 h-3 bg-green-500 rounded-full",
                                      title: `Very Positive: ${day.distribution.very_positive}`
                                    }, null, 8, ["title"]),
                                    createVNode("div", {
                                      class: "w-3 h-3 bg-green-400 rounded-full",
                                      title: `Positive: ${day.distribution.positive}`
                                    }, null, 8, ["title"]),
                                    createVNode("div", {
                                      class: "w-3 h-3 bg-gray-400 rounded-full",
                                      title: `Neutral: ${day.distribution.neutral}`
                                    }, null, 8, ["title"]),
                                    createVNode("div", {
                                      class: "w-3 h-3 bg-red-400 rounded-full",
                                      title: `Negative: ${day.distribution.negative}`
                                    }, null, 8, ["title"]),
                                    createVNode("div", {
                                      class: "w-3 h-3 bg-red-500 rounded-full",
                                      title: `Very Negative: ${day.distribution.very_negative}`
                                    }, null, 8, ["title"])
                                  ])
                                ])
                              ]);
                            }), 128))
                          ])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-gray-50 rounded-lg p-6" }, [
                    createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-4" }, "Explore Different Views"),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-3 gap-4" }, [
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.platform", { platform: __props.platform, category: __props.category, days: 7 }),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Last 7 Days"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Short-term trends")
                        ]),
                        _: 1
                      }, 8, ["href"]),
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.platform", { platform: __props.platform, category: __props.category, days: 90 }),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Last 90 Days"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Long-term analysis")
                        ]),
                        _: 1
                      }, 8, ["href"]),
                      createVNode(unref(Link), {
                        href: route("sentiment-analysis.chart", { platform: __props.platform, category: __props.category }),
                        class: "text-center p-3 bg-white rounded border hover:bg-gray-50"
                      }, {
                        default: withCtx(() => [
                          createVNode("div", { class: "font-medium" }, "Price Correlation"),
                          createVNode("div", { class: "text-sm text-gray-500" }, "vs cryptocurrency prices")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/SentimentAnalysis/PlatformAnalysis.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
