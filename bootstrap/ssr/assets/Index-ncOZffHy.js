import { unref, withCtx, createVNode, createBlock, openBlock, createTextVNode, createCommentVNode, toDisplayString, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderClass, ssrInterpolate, ssrRenderList, ssrRenderStyle } from "vue/server-renderer";
import { Head, Link } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./AuthenticatedLayout-8TbwyeTu.js";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-1tPrXgE0.js";
import "./ApplicationLogo-B2173abF.js";
const _sfc_main = {
  __name: "Index",
  __ssrInlineRender: true,
  props: {
    recentSentiment: {
      type: Object,
      default: () => ({
        current_sentiment: 0,
        trend: "neutral",
        change_7d: 0,
        total_posts_7d: 0,
        daily_data: []
      })
    },
    availableFilters: {
      type: Object,
      default: () => ({
        platforms: [],
        categories: []
      })
    }
  },
  setup(__props) {
    const getSentimentColor = (sentiment) => {
      if (!sentiment) return "text-gray-500";
      if (sentiment > 0.2) return "text-green-600";
      if (sentiment < -0.2) return "text-red-600";
      return "text-gray-600";
    };
    const getTrendColor = (trend) => {
      return {
        "positive": "text-green-600",
        "negative": "text-red-600",
        "neutral": "text-gray-600"
      }[trend] || "text-gray-600";
    };
    const getTrendLabel = (trend) => {
      return {
        "positive": "↗ Trending Up",
        "negative": "↘ Trending Down",
        "neutral": "→ Stable"
      }[trend] || "No Trend";
    };
    const getChangeColor = (change) => {
      if (!change) return "text-gray-500";
      return change > 0 ? "text-green-600" : "text-red-600";
    };
    const getSentimentBarColor = (sentiment) => {
      if (sentiment > 0.2) return "bg-green-400 hover:bg-green-500";
      if (sentiment < -0.2) return "bg-red-400 hover:bg-red-500";
      return "bg-gray-400 hover:bg-gray-500";
    };
    const getBarHeight = (sentiment) => {
      return (sentiment + 1) / 2 * 80 + 10;
    };
    const formatNumber = (num) => {
      if (!num) return "0";
      if (num >= 1e6) return (num / 1e6).toFixed(1) + "M";
      if (num >= 1e3) return (num / 1e3).toFixed(1) + "K";
      return num.toString();
    };
    const route = (name, params = {}) => {
      const routes = {
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
      _push(ssrRenderComponent(unref(Head), { title: "Sentiment Analysis Dashboard" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$1, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex justify-between items-center" data-v-d0eed5c8${_scopeId}><div data-v-d0eed5c8${_scopeId}><h2 class="font-semibold text-xl text-gray-800 leading-tight" data-v-d0eed5c8${_scopeId}> Sentiment Analysis Dashboard </h2><p class="mt-1 text-sm text-gray-600" data-v-d0eed5c8${_scopeId}> Monitor social media sentiment trends and market correlations </p></div><div class="flex items-center space-x-3" data-v-d0eed5c8${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.chart"),
              class: "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-d0eed5c8${_scopeId2}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" data-v-d0eed5c8${_scopeId2}></path></svg> View Charts `);
                } else {
                  return [
                    (openBlock(), createBlock("svg", {
                      class: "-ml-1 mr-2 h-4 w-4",
                      fill: "none",
                      viewBox: "0 0 24 24",
                      stroke: "currentColor"
                    }, [
                      createVNode("path", {
                        "stroke-linecap": "round",
                        "stroke-linejoin": "round",
                        "stroke-width": "2",
                        d: "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                      })
                    ])),
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
                  createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, " Sentiment Analysis Dashboard "),
                  createVNode("p", { class: "mt-1 text-sm text-gray-600" }, " Monitor social media sentiment trends and market correlations ")
                ]),
                createVNode("div", { class: "flex items-center space-x-3" }, [
                  createVNode(unref(Link), {
                    href: route("sentiment-analysis.chart"),
                    class: "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  }, {
                    default: withCtx(() => [
                      (openBlock(), createBlock("svg", {
                        class: "-ml-1 mr-2 h-4 w-4",
                        fill: "none",
                        viewBox: "0 0 24 24",
                        stroke: "currentColor"
                      }, [
                        createVNode("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        })
                      ])),
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
            _push2(`<div class="py-12" data-v-d0eed5c8${_scopeId}><div class="max-w-7xl mx-auto sm:px-6 lg:px-8" data-v-d0eed5c8${_scopeId}><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" data-v-d0eed5c8${_scopeId}><div class="p-6" data-v-d0eed5c8${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6" data-v-d0eed5c8${_scopeId}>Recent Sentiment Overview</h3><div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6" data-v-d0eed5c8${_scopeId}><div class="text-center" data-v-d0eed5c8${_scopeId}><div class="${ssrRenderClass([getSentimentColor(__props.recentSentiment.current_sentiment), "text-2xl font-bold"])}" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(((_a = __props.recentSentiment.current_sentiment) == null ? void 0 : _a.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId}>Current Sentiment</div><div class="${ssrRenderClass([getTrendColor(__props.recentSentiment.trend), "text-xs mt-1"])}" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(getTrendLabel(__props.recentSentiment.trend))}</div></div><div class="text-center" data-v-d0eed5c8${_scopeId}><div class="${ssrRenderClass([getChangeColor(__props.recentSentiment.change_7d), "text-2xl font-bold"])}" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(__props.recentSentiment.change_7d >= 0 ? "+" : "")}${ssrInterpolate(((_b = __props.recentSentiment.change_7d) == null ? void 0 : _b.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId}>7-Day Change</div><div class="text-xs text-gray-400 mt-1" data-v-d0eed5c8${_scopeId}>vs last week</div></div><div class="text-center" data-v-d0eed5c8${_scopeId}><div class="text-2xl font-bold text-gray-900" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(formatNumber(__props.recentSentiment.total_posts_7d))}</div><div class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId}>Total Posts</div><div class="text-xs text-gray-400 mt-1" data-v-d0eed5c8${_scopeId}>last 7 days</div></div><div class="text-center" data-v-d0eed5c8${_scopeId}><div class="text-2xl font-bold text-gray-900" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(((_c = __props.recentSentiment.daily_data) == null ? void 0 : _c.length) || 0)}</div><div class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId}>Days Tracked</div><div class="text-xs text-gray-400 mt-1" data-v-d0eed5c8${_scopeId}>with data</div></div></div>`);
            if (__props.recentSentiment.daily_data && __props.recentSentiment.daily_data.length > 0) {
              _push2(`<div data-v-d0eed5c8${_scopeId}><h4 class="text-sm font-medium text-gray-700 mb-3" data-v-d0eed5c8${_scopeId}>7-Day Sentiment Trend</h4><div class="flex items-end space-x-1 h-20" data-v-d0eed5c8${_scopeId}><!--[-->`);
              ssrRenderList(__props.recentSentiment.daily_data, (day, index) => {
                _push2(`<div class="${ssrRenderClass([getSentimentBarColor(day.sentiment), "flex-1 bg-gray-200 rounded-t relative group cursor-pointer transition-colors"])}" style="${ssrRenderStyle({ height: getBarHeight(day.sentiment) + "%" })}" data-v-d0eed5c8${_scopeId}><div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none" data-v-d0eed5c8${_scopeId}><div class="bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap" data-v-d0eed5c8${_scopeId}><div data-v-d0eed5c8${_scopeId}>${ssrInterpolate(new Date(day.date).toLocaleDateString())}</div><div data-v-d0eed5c8${_scopeId}>Sentiment: ${ssrInterpolate(day.sentiment.toFixed(3))}</div><div data-v-d0eed5c8${_scopeId}>Posts: ${ssrInterpolate(formatNumber(day.posts))}</div></div></div></div>`);
              });
              _push2(`<!--]--></div><div class="flex justify-between text-xs text-gray-500 mt-1" data-v-d0eed5c8${_scopeId}><span data-v-d0eed5c8${_scopeId}>${ssrInterpolate(new Date((_d = __props.recentSentiment.daily_data[0]) == null ? void 0 : _d.date).toLocaleDateString())}</span><span data-v-d0eed5c8${_scopeId}>${ssrInterpolate(new Date((_e = __props.recentSentiment.daily_data[__props.recentSentiment.daily_data.length - 1]) == null ? void 0 : _e.date).toLocaleDateString())}</span></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8" data-v-d0eed5c8${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.chart", { coin: "bitcoin", days: 30 }),
              class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex items-center" data-v-d0eed5c8${_scopeId2}><div class="flex-shrink-0" data-v-d0eed5c8${_scopeId2}><div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center" data-v-d0eed5c8${_scopeId2}><svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-d0eed5c8${_scopeId2}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" data-v-d0eed5c8${_scopeId2}></path></svg></div></div><div class="ml-4" data-v-d0eed5c8${_scopeId2}><h3 class="text-lg font-medium text-gray-900" data-v-d0eed5c8${_scopeId2}>Price Correlation</h3><p class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId2}>Analyze sentiment vs cryptocurrency prices</p></div></div>`);
                } else {
                  return [
                    createVNode("div", { class: "flex items-center" }, [
                      createVNode("div", { class: "flex-shrink-0" }, [
                        createVNode("div", { class: "w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-indigo-600",
                            fill: "none",
                            viewBox: "0 0 24 24",
                            stroke: "currentColor"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            })
                          ]))
                        ])
                      ]),
                      createVNode("div", { class: "ml-4" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Price Correlation"),
                        createVNode("p", { class: "text-sm text-gray-500" }, "Analyze sentiment vs cryptocurrency prices")
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "twitter", days: 30 }),
              class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex items-center" data-v-d0eed5c8${_scopeId2}><div class="flex-shrink-0" data-v-d0eed5c8${_scopeId2}><div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center" data-v-d0eed5c8${_scopeId2}><svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-d0eed5c8${_scopeId2}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" data-v-d0eed5c8${_scopeId2}></path></svg></div></div><div class="ml-4" data-v-d0eed5c8${_scopeId2}><h3 class="text-lg font-medium text-gray-900" data-v-d0eed5c8${_scopeId2}>Platform Analysis</h3><p class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId2}>Deep dive into platform-specific sentiment</p></div></div>`);
                } else {
                  return [
                    createVNode("div", { class: "flex items-center" }, [
                      createVNode("div", { class: "flex-shrink-0" }, [
                        createVNode("div", { class: "w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-blue-600",
                            fill: "none",
                            viewBox: "0 0 24 24",
                            stroke: "currentColor"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"
                            })
                          ]))
                        ])
                      ]),
                      createVNode("div", { class: "ml-4" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Platform Analysis"),
                        createVNode("p", { class: "text-sm text-gray-500" }, "Deep dive into platform-specific sentiment")
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.trends", { timeframe: "90d" }),
              class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex items-center" data-v-d0eed5c8${_scopeId2}><div class="flex-shrink-0" data-v-d0eed5c8${_scopeId2}><div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center" data-v-d0eed5c8${_scopeId2}><svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-d0eed5c8${_scopeId2}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" data-v-d0eed5c8${_scopeId2}></path></svg></div></div><div class="ml-4" data-v-d0eed5c8${_scopeId2}><h3 class="text-lg font-medium text-gray-900" data-v-d0eed5c8${_scopeId2}>Trend Analysis</h3><p class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId2}>Long-term sentiment trends and patterns</p></div></div>`);
                } else {
                  return [
                    createVNode("div", { class: "flex items-center" }, [
                      createVNode("div", { class: "flex-shrink-0" }, [
                        createVNode("div", { class: "w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-green-600",
                            fill: "none",
                            viewBox: "0 0 24 24",
                            stroke: "currentColor"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                            })
                          ]))
                        ])
                      ]),
                      createVNode("div", { class: "ml-4" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Trend Analysis"),
                        createVNode("p", { class: "text-sm text-gray-500" }, "Long-term sentiment trends and patterns")
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.correlations"),
              class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex items-center" data-v-d0eed5c8${_scopeId2}><div class="flex-shrink-0" data-v-d0eed5c8${_scopeId2}><div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center" data-v-d0eed5c8${_scopeId2}><svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-d0eed5c8${_scopeId2}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" data-v-d0eed5c8${_scopeId2}></path></svg></div></div><div class="ml-4" data-v-d0eed5c8${_scopeId2}><h3 class="text-lg font-medium text-gray-900" data-v-d0eed5c8${_scopeId2}>Multi-Coin Analysis</h3><p class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId2}>Compare sentiment across cryptocurrencies</p></div></div>`);
                } else {
                  return [
                    createVNode("div", { class: "flex items-center" }, [
                      createVNode("div", { class: "flex-shrink-0" }, [
                        createVNode("div", { class: "w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-purple-600",
                            fill: "none",
                            viewBox: "0 0 24 24",
                            stroke: "currentColor"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                            })
                          ]))
                        ])
                      ]),
                      createVNode("div", { class: "ml-4" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Multi-Coin Analysis"),
                        createVNode("p", { class: "text-sm text-gray-500" }, "Compare sentiment across cryptocurrencies")
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "twitter", category: "blockchain" }),
              class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex items-center" data-v-d0eed5c8${_scopeId2}><div class="flex-shrink-0" data-v-d0eed5c8${_scopeId2}><div class="w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center" data-v-d0eed5c8${_scopeId2}><svg class="w-5 h-5 text-cyan-600" fill="currentColor" viewBox="0 0 24 24" data-v-d0eed5c8${_scopeId2}><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" data-v-d0eed5c8${_scopeId2}></path></svg></div></div><div class="ml-4" data-v-d0eed5c8${_scopeId2}><h3 class="text-lg font-medium text-gray-900" data-v-d0eed5c8${_scopeId2}>Twitter Sentiment</h3><p class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId2}>Twitter-specific blockchain sentiment</p></div></div>`);
                } else {
                  return [
                    createVNode("div", { class: "flex items-center" }, [
                      createVNode("div", { class: "flex-shrink-0" }, [
                        createVNode("div", { class: "w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-cyan-600",
                            fill: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", { d: "M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" })
                          ]))
                        ])
                      ]),
                      createVNode("div", { class: "ml-4" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Twitter Sentiment"),
                        createVNode("p", { class: "text-sm text-gray-500" }, "Twitter-specific blockchain sentiment")
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "reddit", category: "defi" }),
              class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex items-center" data-v-d0eed5c8${_scopeId2}><div class="flex-shrink-0" data-v-d0eed5c8${_scopeId2}><div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center" data-v-d0eed5c8${_scopeId2}><svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 24 24" data-v-d0eed5c8${_scopeId2}><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z" data-v-d0eed5c8${_scopeId2}></path></svg></div></div><div class="ml-4" data-v-d0eed5c8${_scopeId2}><h3 class="text-lg font-medium text-gray-900" data-v-d0eed5c8${_scopeId2}>Reddit Sentiment</h3><p class="text-sm text-gray-500" data-v-d0eed5c8${_scopeId2}>Reddit DeFi community sentiment</p></div></div>`);
                } else {
                  return [
                    createVNode("div", { class: "flex items-center" }, [
                      createVNode("div", { class: "flex-shrink-0" }, [
                        createVNode("div", { class: "w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-orange-600",
                            fill: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", { d: "M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z" })
                          ]))
                        ])
                      ]),
                      createVNode("div", { class: "ml-4" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Reddit Sentiment"),
                        createVNode("p", { class: "text-sm text-gray-500" }, "Reddit DeFi community sentiment")
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div>`);
            if (__props.availableFilters.platforms.length > 0) {
              _push2(`<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" data-v-d0eed5c8${_scopeId}><div class="p-6" data-v-d0eed5c8${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6" data-v-d0eed5c8${_scopeId}>Available Data Sources</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-6" data-v-d0eed5c8${_scopeId}><div data-v-d0eed5c8${_scopeId}><h4 class="text-sm font-medium text-gray-700 mb-3" data-v-d0eed5c8${_scopeId}>Platforms</h4><div class="space-y-2" data-v-d0eed5c8${_scopeId}><!--[-->`);
              ssrRenderList(__props.availableFilters.platforms, (platform) => {
                _push2(`<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg" data-v-d0eed5c8${_scopeId}><div class="flex items-center" data-v-d0eed5c8${_scopeId}><div class="w-2 h-2 bg-blue-500 rounded-full mr-3" data-v-d0eed5c8${_scopeId}></div><span class="text-sm font-medium text-gray-900 capitalize" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(platform)}</span></div>`);
                _push2(ssrRenderComponent(unref(Link), {
                  href: route("sentiment-analysis.platform", { platform }),
                  class: "text-xs text-indigo-600 hover:text-indigo-500"
                }, {
                  default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                    if (_push3) {
                      _push3(` View Analysis → `);
                    } else {
                      return [
                        createTextVNode(" View Analysis → ")
                      ];
                    }
                  }),
                  _: 2
                }, _parent2, _scopeId));
                _push2(`</div>`);
              });
              _push2(`<!--]--></div></div><div data-v-d0eed5c8${_scopeId}><h4 class="text-sm font-medium text-gray-700 mb-3" data-v-d0eed5c8${_scopeId}>Categories</h4><div class="space-y-2" data-v-d0eed5c8${_scopeId}><!--[-->`);
              ssrRenderList(__props.availableFilters.categories, (category) => {
                _push2(`<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg" data-v-d0eed5c8${_scopeId}><div class="flex items-center" data-v-d0eed5c8${_scopeId}><div class="w-2 h-2 bg-green-500 rounded-full mr-3" data-v-d0eed5c8${_scopeId}></div><span class="text-sm font-medium text-gray-900 capitalize" data-v-d0eed5c8${_scopeId}>${ssrInterpolate(category)}</span></div>`);
                _push2(ssrRenderComponent(unref(Link), {
                  href: route("sentiment-analysis.platform", { category }),
                  class: "text-xs text-indigo-600 hover:text-indigo-500"
                }, {
                  default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                    if (_push3) {
                      _push3(` View Analysis → `);
                    } else {
                      return [
                        createTextVNode(" View Analysis → ")
                      ];
                    }
                  }),
                  _: 2
                }, _parent2, _scopeId));
                _push2(`</div>`);
              });
              _push2(`<!--]--></div></div></div></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="bg-blue-50 border border-blue-200 rounded-lg p-6" data-v-d0eed5c8${_scopeId}><h3 class="text-lg font-medium text-blue-900 mb-4" data-v-d0eed5c8${_scopeId}>Getting Started</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-6" data-v-d0eed5c8${_scopeId}><div data-v-d0eed5c8${_scopeId}><h4 class="font-medium text-blue-900 mb-2" data-v-d0eed5c8${_scopeId}>Understanding Sentiment Scores</h4><ul class="text-sm text-blue-800 space-y-1" data-v-d0eed5c8${_scopeId}><li data-v-d0eed5c8${_scopeId}>• <strong data-v-d0eed5c8${_scopeId}>+0.6 to +1.0:</strong> Very positive sentiment</li><li data-v-d0eed5c8${_scopeId}>• <strong data-v-d0eed5c8${_scopeId}>+0.2 to +0.6:</strong> Positive sentiment</li><li data-v-d0eed5c8${_scopeId}>• <strong data-v-d0eed5c8${_scopeId}>-0.2 to +0.2:</strong> Neutral sentiment</li><li data-v-d0eed5c8${_scopeId}>• <strong data-v-d0eed5c8${_scopeId}>-0.6 to -0.2:</strong> Negative sentiment</li><li data-v-d0eed5c8${_scopeId}>• <strong data-v-d0eed5c8${_scopeId}>-1.0 to -0.6:</strong> Very negative sentiment</li></ul></div><div data-v-d0eed5c8${_scopeId}><h4 class="font-medium text-blue-900 mb-2" data-v-d0eed5c8${_scopeId}>Analysis Tips</h4><ul class="text-sm text-blue-800 space-y-1" data-v-d0eed5c8${_scopeId}><li data-v-d0eed5c8${_scopeId}>• Compare sentiment across multiple timeframes</li><li data-v-d0eed5c8${_scopeId}>• Look for correlation with major market events</li><li data-v-d0eed5c8${_scopeId}>• Consider volume and volatility alongside sentiment</li><li data-v-d0eed5c8${_scopeId}>• Use platform-specific analysis for deeper insights</li></ul></div></div></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "max-w-7xl mx-auto sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Recent Sentiment Overview"),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-4 gap-6 mb-6" }, [
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", {
                            class: ["text-2xl font-bold", getSentimentColor(__props.recentSentiment.current_sentiment)]
                          }, toDisplayString(((_f = __props.recentSentiment.current_sentiment) == null ? void 0 : _f.toFixed(3)) || "N/A"), 3),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Current Sentiment"),
                          createVNode("div", {
                            class: ["text-xs mt-1", getTrendColor(__props.recentSentiment.trend)]
                          }, toDisplayString(getTrendLabel(__props.recentSentiment.trend)), 3)
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", {
                            class: ["text-2xl font-bold", getChangeColor(__props.recentSentiment.change_7d)]
                          }, toDisplayString(__props.recentSentiment.change_7d >= 0 ? "+" : "") + toDisplayString(((_g = __props.recentSentiment.change_7d) == null ? void 0 : _g.toFixed(3)) || "N/A"), 3),
                          createVNode("div", { class: "text-sm text-gray-500" }, "7-Day Change"),
                          createVNode("div", { class: "text-xs text-gray-400 mt-1" }, "vs last week")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(formatNumber(__props.recentSentiment.total_posts_7d)), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Total Posts"),
                          createVNode("div", { class: "text-xs text-gray-400 mt-1" }, "last 7 days")
                        ]),
                        createVNode("div", { class: "text-center" }, [
                          createVNode("div", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(((_h = __props.recentSentiment.daily_data) == null ? void 0 : _h.length) || 0), 1),
                          createVNode("div", { class: "text-sm text-gray-500" }, "Days Tracked"),
                          createVNode("div", { class: "text-xs text-gray-400 mt-1" }, "with data")
                        ])
                      ]),
                      __props.recentSentiment.daily_data && __props.recentSentiment.daily_data.length > 0 ? (openBlock(), createBlock("div", { key: 0 }, [
                        createVNode("h4", { class: "text-sm font-medium text-gray-700 mb-3" }, "7-Day Sentiment Trend"),
                        createVNode("div", { class: "flex items-end space-x-1 h-20" }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.recentSentiment.daily_data, (day, index) => {
                            return openBlock(), createBlock("div", {
                              key: index,
                              class: ["flex-1 bg-gray-200 rounded-t relative group cursor-pointer transition-colors", getSentimentBarColor(day.sentiment)],
                              style: { height: getBarHeight(day.sentiment) + "%" }
                            }, [
                              createVNode("div", { class: "absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none" }, [
                                createVNode("div", { class: "bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap" }, [
                                  createVNode("div", null, toDisplayString(new Date(day.date).toLocaleDateString()), 1),
                                  createVNode("div", null, "Sentiment: " + toDisplayString(day.sentiment.toFixed(3)), 1),
                                  createVNode("div", null, "Posts: " + toDisplayString(formatNumber(day.posts)), 1)
                                ])
                              ])
                            ], 6);
                          }), 128))
                        ]),
                        createVNode("div", { class: "flex justify-between text-xs text-gray-500 mt-1" }, [
                          createVNode("span", null, toDisplayString(new Date((_i = __props.recentSentiment.daily_data[0]) == null ? void 0 : _i.date).toLocaleDateString()), 1),
                          createVNode("span", null, toDisplayString(new Date((_j = __props.recentSentiment.daily_data[__props.recentSentiment.daily_data.length - 1]) == null ? void 0 : _j.date).toLocaleDateString()), 1)
                        ])
                      ])) : createCommentVNode("", true)
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8" }, [
                    createVNode(unref(Link), {
                      href: route("sentiment-analysis.chart", { coin: "bitcoin", days: 30 }),
                      class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode("div", { class: "w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center" }, [
                              (openBlock(), createBlock("svg", {
                                class: "w-5 h-5 text-indigo-600",
                                fill: "none",
                                viewBox: "0 0 24 24",
                                stroke: "currentColor"
                              }, [
                                createVNode("path", {
                                  "stroke-linecap": "round",
                                  "stroke-linejoin": "round",
                                  "stroke-width": "2",
                                  d: "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                })
                              ]))
                            ])
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Price Correlation"),
                            createVNode("p", { class: "text-sm text-gray-500" }, "Analyze sentiment vs cryptocurrency prices")
                          ])
                        ])
                      ]),
                      _: 1
                    }, 8, ["href"]),
                    createVNode(unref(Link), {
                      href: route("sentiment-analysis.platform", { platform: "twitter", days: 30 }),
                      class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode("div", { class: "w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center" }, [
                              (openBlock(), createBlock("svg", {
                                class: "w-5 h-5 text-blue-600",
                                fill: "none",
                                viewBox: "0 0 24 24",
                                stroke: "currentColor"
                              }, [
                                createVNode("path", {
                                  "stroke-linecap": "round",
                                  "stroke-linejoin": "round",
                                  "stroke-width": "2",
                                  d: "M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"
                                })
                              ]))
                            ])
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Platform Analysis"),
                            createVNode("p", { class: "text-sm text-gray-500" }, "Deep dive into platform-specific sentiment")
                          ])
                        ])
                      ]),
                      _: 1
                    }, 8, ["href"]),
                    createVNode(unref(Link), {
                      href: route("sentiment-analysis.trends", { timeframe: "90d" }),
                      class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode("div", { class: "w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center" }, [
                              (openBlock(), createBlock("svg", {
                                class: "w-5 h-5 text-green-600",
                                fill: "none",
                                viewBox: "0 0 24 24",
                                stroke: "currentColor"
                              }, [
                                createVNode("path", {
                                  "stroke-linecap": "round",
                                  "stroke-linejoin": "round",
                                  "stroke-width": "2",
                                  d: "M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                })
                              ]))
                            ])
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Trend Analysis"),
                            createVNode("p", { class: "text-sm text-gray-500" }, "Long-term sentiment trends and patterns")
                          ])
                        ])
                      ]),
                      _: 1
                    }, 8, ["href"]),
                    createVNode(unref(Link), {
                      href: route("sentiment-analysis.correlations"),
                      class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode("div", { class: "w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center" }, [
                              (openBlock(), createBlock("svg", {
                                class: "w-5 h-5 text-purple-600",
                                fill: "none",
                                viewBox: "0 0 24 24",
                                stroke: "currentColor"
                              }, [
                                createVNode("path", {
                                  "stroke-linecap": "round",
                                  "stroke-linejoin": "round",
                                  "stroke-width": "2",
                                  d: "M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                })
                              ]))
                            ])
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Multi-Coin Analysis"),
                            createVNode("p", { class: "text-sm text-gray-500" }, "Compare sentiment across cryptocurrencies")
                          ])
                        ])
                      ]),
                      _: 1
                    }, 8, ["href"]),
                    createVNode(unref(Link), {
                      href: route("sentiment-analysis.platform", { platform: "twitter", category: "blockchain" }),
                      class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode("div", { class: "w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center" }, [
                              (openBlock(), createBlock("svg", {
                                class: "w-5 h-5 text-cyan-600",
                                fill: "currentColor",
                                viewBox: "0 0 24 24"
                              }, [
                                createVNode("path", { d: "M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" })
                              ]))
                            ])
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Twitter Sentiment"),
                            createVNode("p", { class: "text-sm text-gray-500" }, "Twitter-specific blockchain sentiment")
                          ])
                        ])
                      ]),
                      _: 1
                    }, 8, ["href"]),
                    createVNode(unref(Link), {
                      href: route("sentiment-analysis.platform", { platform: "reddit", category: "defi" }),
                      class: "block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode("div", { class: "w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center" }, [
                              (openBlock(), createBlock("svg", {
                                class: "w-5 h-5 text-orange-600",
                                fill: "currentColor",
                                viewBox: "0 0 24 24"
                              }, [
                                createVNode("path", { d: "M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z" })
                              ]))
                            ])
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("h3", { class: "text-lg font-medium text-gray-900" }, "Reddit Sentiment"),
                            createVNode("p", { class: "text-sm text-gray-500" }, "Reddit DeFi community sentiment")
                          ])
                        ])
                      ]),
                      _: 1
                    }, 8, ["href"])
                  ]),
                  __props.availableFilters.platforms.length > 0 ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"
                  }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Available Data Sources"),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6" }, [
                        createVNode("div", null, [
                          createVNode("h4", { class: "text-sm font-medium text-gray-700 mb-3" }, "Platforms"),
                          createVNode("div", { class: "space-y-2" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.availableFilters.platforms, (platform) => {
                              return openBlock(), createBlock("div", {
                                key: platform,
                                class: "flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                              }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "w-2 h-2 bg-blue-500 rounded-full mr-3" }),
                                  createVNode("span", { class: "text-sm font-medium text-gray-900 capitalize" }, toDisplayString(platform), 1)
                                ]),
                                createVNode(unref(Link), {
                                  href: route("sentiment-analysis.platform", { platform }),
                                  class: "text-xs text-indigo-600 hover:text-indigo-500"
                                }, {
                                  default: withCtx(() => [
                                    createTextVNode(" View Analysis → ")
                                  ]),
                                  _: 2
                                }, 1032, ["href"])
                              ]);
                            }), 128))
                          ])
                        ]),
                        createVNode("div", null, [
                          createVNode("h4", { class: "text-sm font-medium text-gray-700 mb-3" }, "Categories"),
                          createVNode("div", { class: "space-y-2" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.availableFilters.categories, (category) => {
                              return openBlock(), createBlock("div", {
                                key: category,
                                class: "flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                              }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "w-2 h-2 bg-green-500 rounded-full mr-3" }),
                                  createVNode("span", { class: "text-sm font-medium text-gray-900 capitalize" }, toDisplayString(category), 1)
                                ]),
                                createVNode(unref(Link), {
                                  href: route("sentiment-analysis.platform", { category }),
                                  class: "text-xs text-indigo-600 hover:text-indigo-500"
                                }, {
                                  default: withCtx(() => [
                                    createTextVNode(" View Analysis → ")
                                  ]),
                                  _: 2
                                }, 1032, ["href"])
                              ]);
                            }), 128))
                          ])
                        ])
                      ])
                    ])
                  ])) : createCommentVNode("", true),
                  createVNode("div", { class: "bg-blue-50 border border-blue-200 rounded-lg p-6" }, [
                    createVNode("h3", { class: "text-lg font-medium text-blue-900 mb-4" }, "Getting Started"),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6" }, [
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-blue-900 mb-2" }, "Understanding Sentiment Scores"),
                        createVNode("ul", { class: "text-sm text-blue-800 space-y-1" }, [
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "+0.6 to +1.0:"),
                            createTextVNode(" Very positive sentiment")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "+0.2 to +0.6:"),
                            createTextVNode(" Positive sentiment")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "-0.2 to +0.2:"),
                            createTextVNode(" Neutral sentiment")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "-0.6 to -0.2:"),
                            createTextVNode(" Negative sentiment")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "-1.0 to -0.6:"),
                            createTextVNode(" Very negative sentiment")
                          ])
                        ])
                      ]),
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-blue-900 mb-2" }, "Analysis Tips"),
                        createVNode("ul", { class: "text-sm text-blue-800 space-y-1" }, [
                          createVNode("li", null, "• Compare sentiment across multiple timeframes"),
                          createVNode("li", null, "• Look for correlation with major market events"),
                          createVNode("li", null, "• Consider volume and volatility alongside sentiment"),
                          createVNode("li", null, "• Use platform-specific analysis for deeper insights")
                        ])
                      ])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/SentimentAnalysis/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Index = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-d0eed5c8"]]);
export {
  Index as default
};
