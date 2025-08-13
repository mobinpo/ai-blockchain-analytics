import { computed, unref, withCtx, createTextVNode, createVNode, toDisplayString, createBlock, openBlock, Fragment, renderList, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderClass, ssrRenderStyle } from "vue/server-renderer";
import { Head, Link } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./AuthenticatedLayout-8TbwyeTu.js";
import "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const _sfc_main = {
  __name: "Correlations",
  __ssrInlineRender: true,
  props: {
    correlations: {
      type: Object,
      default: () => ({
        coin_correlations: [],
        analysis_period: {
          start_date: "",
          end_date: "",
          days: 0
        }
      })
    }
  },
  setup(__props) {
    const props = __props;
    const strongestPositiveCorrelation = computed(() => {
      if (!props.correlations.coin_correlations) return null;
      return props.correlations.coin_correlations.filter((coin) => coin.correlation > 0).sort((a, b) => b.correlation - a.correlation)[0];
    });
    const strongestNegativeCorrelation = computed(() => {
      if (!props.correlations.coin_correlations) return null;
      return props.correlations.coin_correlations.filter((coin) => coin.correlation < 0).sort((a, b) => a.correlation - b.correlation)[0];
    });
    const weakestCorrelation = computed(() => {
      if (!props.correlations.coin_correlations) return null;
      return props.correlations.coin_correlations.sort((a, b) => Math.abs(a.correlation) - Math.abs(b.correlation))[0];
    });
    const averageCorrelation = computed(() => {
      if (!props.correlations.coin_correlations || props.correlations.coin_correlations.length === 0) return null;
      const sum = props.correlations.coin_correlations.reduce((acc, coin) => acc + coin.correlation, 0);
      return sum / props.correlations.coin_correlations.length;
    });
    const formatCoinName = (coinId) => {
      const names = {
        "bitcoin": "Bitcoin",
        "ethereum": "Ethereum",
        "binancecoin": "BNB",
        "cardano": "Cardano",
        "solana": "Solana",
        "polkadot": "Polkadot",
        "chainlink": "Chainlink",
        "polygon": "Polygon"
      };
      return names[coinId] || coinId.charAt(0).toUpperCase() + coinId.slice(1);
    };
    const getCorrelationColor = (correlation) => {
      if (!correlation) return "text-gray-500";
      const abs = Math.abs(correlation);
      if (abs >= 0.6) return correlation > 0 ? "text-green-600" : "text-red-600";
      if (abs >= 0.3) return correlation > 0 ? "text-green-500" : "text-red-500";
      return "text-gray-500";
    };
    const getCorrelationBarColor = (correlation) => {
      if (!correlation) return "bg-gray-400";
      const abs = Math.abs(correlation);
      if (abs >= 0.6) return correlation > 0 ? "bg-green-500" : "bg-red-500";
      if (abs >= 0.3) return correlation > 0 ? "bg-green-400" : "bg-red-400";
      return "bg-gray-400";
    };
    const getCorrelationStrength = (correlation) => {
      if (!correlation) return "No Data";
      const abs = Math.abs(correlation);
      const strength = abs >= 0.8 ? "Very Strong" : abs >= 0.6 ? "Strong" : abs >= 0.4 ? "Moderate" : abs >= 0.2 ? "Weak" : "Very Weak";
      const direction = correlation > 0 ? "Positive" : "Negative";
      return `${strength} ${direction}`;
    };
    const getSentimentColor = (sentiment) => {
      if (!sentiment) return "text-gray-500";
      if (sentiment > 0.2) return "text-green-600";
      if (sentiment < -0.2) return "text-red-600";
      return "text-gray-600";
    };
    const getPriceChangeColor = (change) => {
      if (!change) return "text-gray-500";
      return change > 0 ? "text-green-600" : "text-red-600";
    };
    const getCorrelationInterpretation = (correlation) => {
      const abs = Math.abs(correlation);
      if (abs >= 0.6) {
        return correlation > 0 ? "Strong positive relationship between sentiment and prices" : "Strong negative relationship between sentiment and prices";
      } else if (abs >= 0.3) {
        return correlation > 0 ? "Moderate positive relationship between sentiment and prices" : "Moderate negative relationship between sentiment and prices";
      } else {
        return "Weak relationship between sentiment and price movements";
      }
    };
    const route = (name, params = {}) => {
      const routes = {
        "sentiment-analysis.index": "/sentiment-analysis",
        "sentiment-analysis.chart": "/sentiment-analysis/chart"
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
      _push(ssrRenderComponent(unref(Head), { title: "Multi-Coin Sentiment Correlations" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$1, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex justify-between items-center"${_scopeId}><div${_scopeId}><h2 class="font-semibold text-xl text-gray-800 leading-tight"${_scopeId}> Multi-Coin Sentiment Correlations </h2><p class="mt-1 text-sm text-gray-600"${_scopeId}> Compare sentiment correlations across multiple cryptocurrencies </p></div><div class="flex items-center space-x-3"${_scopeId}>`);
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
                  createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, " Multi-Coin Sentiment Correlations "),
                  createVNode("p", { class: "mt-1 text-sm text-gray-600" }, " Compare sentiment correlations across multiple cryptocurrencies ")
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
          var _a, _b, _c, _d, _e, _f, _g, _h;
          if (_push2) {
            _push2(`<div class="py-12"${_scopeId}><div class="max-w-7xl mx-auto sm:px-6 lg:px-8"${_scopeId}><div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8"${_scopeId}><h3 class="text-lg font-medium text-blue-900 mb-2"${_scopeId}>Analysis Period</h3><p class="text-blue-800"${_scopeId}><strong${_scopeId}>${ssrInterpolate((_a = __props.correlations.analysis_period) == null ? void 0 : _a.start_date)}</strong> to <strong${_scopeId}>${ssrInterpolate((_b = __props.correlations.analysis_period) == null ? void 0 : _b.end_date)}</strong> (${ssrInterpolate((_c = __props.correlations.analysis_period) == null ? void 0 : _c.days)} days) </p><p class="text-sm text-blue-700 mt-1"${_scopeId}> Correlations calculated using sentiment data from social media and price data from CoinGecko </p></div><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Sentiment vs Price Correlations</h3>`);
            if (__props.correlations.coin_correlations && __props.correlations.coin_correlations.length > 0) {
              _push2(`<div${_scopeId}><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"${_scopeId}><!--[-->`);
              ssrRenderList(__props.correlations.coin_correlations, (coin) => {
                var _a2, _b2, _c2;
                _push2(`<div class="bg-gray-50 rounded-lg p-6 hover:bg-gray-100 transition-colors"${_scopeId}><div class="flex justify-between items-center mb-4"${_scopeId}><h4 class="font-medium text-gray-900 capitalize"${_scopeId}>${ssrInterpolate(formatCoinName(coin.coin_id))}</h4><span class="text-xs text-gray-500"${_scopeId}>${ssrInterpolate(coin.data_points)} data points </span></div><div class="text-center mb-4"${_scopeId}><div class="${ssrRenderClass([getCorrelationColor(coin.correlation), "text-3xl font-bold"])}"${_scopeId}>${ssrInterpolate(((_a2 = coin.correlation) == null ? void 0 : _a2.toFixed(3)) || "N/A")}</div><div class="text-sm text-gray-600"${_scopeId}>${ssrInterpolate(getCorrelationStrength(coin.correlation))}</div></div><div class="mb-4"${_scopeId}><div class="bg-gray-200 rounded-full h-3 relative"${_scopeId}><div class="${ssrRenderClass([getCorrelationBarColor(coin.correlation), "h-3 rounded-full transition-all duration-500"])}" style="${ssrRenderStyle({
                  width: Math.abs(coin.correlation || 0) * 100 + "%",
                  marginLeft: coin.correlation < 0 ? 50 - Math.abs(coin.correlation) * 50 + "%" : "50%"
                })}"${_scopeId}></div><div class="absolute left-1/2 top-0 w-px h-3 bg-gray-400 transform -translate-x-1/2"${_scopeId}></div></div><div class="flex justify-between text-xs text-gray-500 mt-1"${_scopeId}><span${_scopeId}>-1.0</span><span${_scopeId}>0</span><span${_scopeId}>+1.0</span></div></div><div class="space-y-2"${_scopeId}><div class="flex justify-between text-sm"${_scopeId}><span class="text-gray-600"${_scopeId}>Avg Sentiment</span><span class="${ssrRenderClass([getSentimentColor(coin.sentiment_avg), "font-medium"])}"${_scopeId}>${ssrInterpolate(((_b2 = coin.sentiment_avg) == null ? void 0 : _b2.toFixed(3)) || "N/A")}</span></div><div class="flex justify-between text-sm"${_scopeId}><span class="text-gray-600"${_scopeId}>Avg Price Change</span><span class="${ssrRenderClass([getPriceChangeColor(coin.price_change_avg), "font-medium"])}"${_scopeId}>${ssrInterpolate(coin.price_change_avg >= 0 ? "+" : "")}${ssrInterpolate(((_c2 = coin.price_change_avg) == null ? void 0 : _c2.toFixed(2)) || "N/A")}% </span></div></div><div class="mt-4"${_scopeId}>`);
                _push2(ssrRenderComponent(unref(Link), {
                  href: route("sentiment-analysis.chart", { coin: coin.coin_id, days: 30 }),
                  class: "block w-full text-center px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                }, {
                  default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                    if (_push3) {
                      _push3(` View Detailed Chart `);
                    } else {
                      return [
                        createTextVNode(" View Detailed Chart ")
                      ];
                    }
                  }),
                  _: 2
                }, _parent2, _scopeId));
                _push2(`</div></div>`);
              });
              _push2(`<!--]--></div></div>`);
            } else {
              _push2(`<div class="text-center py-12"${_scopeId}><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"${_scopeId}></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900"${_scopeId}>No correlation data available</h3><p class="mt-1 text-sm text-gray-500"${_scopeId}>Correlation analysis requires sufficient sentiment and price data.</p></div>`);
            }
            _push2(`</div></div><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8"${_scopeId}><div class="p-6"${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-6"${_scopeId}>Correlation Insights</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-6"${_scopeId}>`);
            if (strongestPositiveCorrelation.value) {
              _push2(`<div class="bg-green-50 rounded-lg p-4"${_scopeId}><h4 class="font-medium text-green-900 mb-2"${_scopeId}>Strongest Positive Correlation</h4><div class="text-2xl font-bold text-green-700 mb-1"${_scopeId}>${ssrInterpolate(formatCoinName(strongestPositiveCorrelation.value.coin_id))}</div><div class="text-green-600"${_scopeId}>${ssrInterpolate(strongestPositiveCorrelation.value.correlation.toFixed(3))} correlation coefficient </div><p class="text-sm text-green-800 mt-2"${_scopeId}> Sentiment and price movements tend to move in the same direction </p></div>`);
            } else {
              _push2(`<!---->`);
            }
            if (strongestNegativeCorrelation.value) {
              _push2(`<div class="bg-red-50 rounded-lg p-4"${_scopeId}><h4 class="font-medium text-red-900 mb-2"${_scopeId}>Strongest Negative Correlation</h4><div class="text-2xl font-bold text-red-700 mb-1"${_scopeId}>${ssrInterpolate(formatCoinName(strongestNegativeCorrelation.value.coin_id))}</div><div class="text-red-600"${_scopeId}>${ssrInterpolate(strongestNegativeCorrelation.value.correlation.toFixed(3))} correlation coefficient </div><p class="text-sm text-red-800 mt-2"${_scopeId}> Sentiment and price movements tend to move in opposite directions </p></div>`);
            } else {
              _push2(`<!---->`);
            }
            if (weakestCorrelation.value) {
              _push2(`<div class="bg-gray-50 rounded-lg p-4"${_scopeId}><h4 class="font-medium text-gray-900 mb-2"${_scopeId}>Weakest Correlation</h4><div class="text-2xl font-bold text-gray-700 mb-1"${_scopeId}>${ssrInterpolate(formatCoinName(weakestCorrelation.value.coin_id))}</div><div class="text-gray-600"${_scopeId}>${ssrInterpolate(weakestCorrelation.value.correlation.toFixed(3))} correlation coefficient </div><p class="text-sm text-gray-800 mt-2"${_scopeId}> Sentiment appears to have little relationship with price movements </p></div>`);
            } else {
              _push2(`<!---->`);
            }
            if (averageCorrelation.value !== null) {
              _push2(`<div class="bg-blue-50 rounded-lg p-4"${_scopeId}><h4 class="font-medium text-blue-900 mb-2"${_scopeId}>Average Correlation</h4><div class="text-2xl font-bold text-blue-700 mb-1"${_scopeId}>${ssrInterpolate(averageCorrelation.value.toFixed(3))}</div><div class="text-blue-600"${_scopeId}> Across ${ssrInterpolate(((_d = __props.correlations.coin_correlations) == null ? void 0 : _d.length) || 0)} cryptocurrencies </div><p class="text-sm text-blue-800 mt-2"${_scopeId}>${ssrInterpolate(getCorrelationInterpretation(averageCorrelation.value))}</p></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div><div class="bg-amber-50 border border-amber-200 rounded-lg p-6"${_scopeId}><h3 class="text-lg font-medium text-amber-900 mb-4"${_scopeId}>Understanding Correlations</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-6"${_scopeId}><div${_scopeId}><h4 class="font-medium text-amber-900 mb-2"${_scopeId}>Correlation Strength</h4><ul class="text-sm text-amber-800 space-y-1"${_scopeId}><li${_scopeId}>• <strong${_scopeId}>±0.8 to ±1.0:</strong> Very strong correlation</li><li${_scopeId}>• <strong${_scopeId}>±0.6 to ±0.8:</strong> Strong correlation</li><li${_scopeId}>• <strong${_scopeId}>±0.4 to ±0.6:</strong> Moderate correlation</li><li${_scopeId}>• <strong${_scopeId}>±0.2 to ±0.4:</strong> Weak correlation</li><li${_scopeId}>• <strong${_scopeId}>0 to ±0.2:</strong> Very weak/no correlation</li></ul></div><div${_scopeId}><h4 class="font-medium text-amber-900 mb-2"${_scopeId}>Important Notes</h4><ul class="text-sm text-amber-800 space-y-1"${_scopeId}><li${_scopeId}>• Correlation doesn&#39;t imply causation</li><li${_scopeId}>• Market conditions affect correlation strength</li><li${_scopeId}>• External events can disrupt typical patterns</li><li${_scopeId}>• Use multiple timeframes for validation</li><li${_scopeId}>• Consider volume and volatility alongside correlation</li></ul></div></div></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "max-w-7xl mx-auto sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8" }, [
                    createVNode("h3", { class: "text-lg font-medium text-blue-900 mb-2" }, "Analysis Period"),
                    createVNode("p", { class: "text-blue-800" }, [
                      createVNode("strong", null, toDisplayString((_e = __props.correlations.analysis_period) == null ? void 0 : _e.start_date), 1),
                      createTextVNode(" to "),
                      createVNode("strong", null, toDisplayString((_f = __props.correlations.analysis_period) == null ? void 0 : _f.end_date), 1),
                      createTextVNode(" (" + toDisplayString((_g = __props.correlations.analysis_period) == null ? void 0 : _g.days) + " days) ", 1)
                    ]),
                    createVNode("p", { class: "text-sm text-blue-700 mt-1" }, " Correlations calculated using sentiment data from social media and price data from CoinGecko ")
                  ]),
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Sentiment vs Price Correlations"),
                      __props.correlations.coin_correlations && __props.correlations.coin_correlations.length > 0 ? (openBlock(), createBlock("div", { key: 0 }, [
                        createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.correlations.coin_correlations, (coin) => {
                            var _a2, _b2, _c2;
                            return openBlock(), createBlock("div", {
                              key: coin.coin_id,
                              class: "bg-gray-50 rounded-lg p-6 hover:bg-gray-100 transition-colors"
                            }, [
                              createVNode("div", { class: "flex justify-between items-center mb-4" }, [
                                createVNode("h4", { class: "font-medium text-gray-900 capitalize" }, toDisplayString(formatCoinName(coin.coin_id)), 1),
                                createVNode("span", { class: "text-xs text-gray-500" }, toDisplayString(coin.data_points) + " data points ", 1)
                              ]),
                              createVNode("div", { class: "text-center mb-4" }, [
                                createVNode("div", {
                                  class: ["text-3xl font-bold", getCorrelationColor(coin.correlation)]
                                }, toDisplayString(((_a2 = coin.correlation) == null ? void 0 : _a2.toFixed(3)) || "N/A"), 3),
                                createVNode("div", { class: "text-sm text-gray-600" }, toDisplayString(getCorrelationStrength(coin.correlation)), 1)
                              ]),
                              createVNode("div", { class: "mb-4" }, [
                                createVNode("div", { class: "bg-gray-200 rounded-full h-3 relative" }, [
                                  createVNode("div", {
                                    class: ["h-3 rounded-full transition-all duration-500", getCorrelationBarColor(coin.correlation)],
                                    style: {
                                      width: Math.abs(coin.correlation || 0) * 100 + "%",
                                      marginLeft: coin.correlation < 0 ? 50 - Math.abs(coin.correlation) * 50 + "%" : "50%"
                                    }
                                  }, null, 6),
                                  createVNode("div", { class: "absolute left-1/2 top-0 w-px h-3 bg-gray-400 transform -translate-x-1/2" })
                                ]),
                                createVNode("div", { class: "flex justify-between text-xs text-gray-500 mt-1" }, [
                                  createVNode("span", null, "-1.0"),
                                  createVNode("span", null, "0"),
                                  createVNode("span", null, "+1.0")
                                ])
                              ]),
                              createVNode("div", { class: "space-y-2" }, [
                                createVNode("div", { class: "flex justify-between text-sm" }, [
                                  createVNode("span", { class: "text-gray-600" }, "Avg Sentiment"),
                                  createVNode("span", {
                                    class: ["font-medium", getSentimentColor(coin.sentiment_avg)]
                                  }, toDisplayString(((_b2 = coin.sentiment_avg) == null ? void 0 : _b2.toFixed(3)) || "N/A"), 3)
                                ]),
                                createVNode("div", { class: "flex justify-between text-sm" }, [
                                  createVNode("span", { class: "text-gray-600" }, "Avg Price Change"),
                                  createVNode("span", {
                                    class: ["font-medium", getPriceChangeColor(coin.price_change_avg)]
                                  }, toDisplayString(coin.price_change_avg >= 0 ? "+" : "") + toDisplayString(((_c2 = coin.price_change_avg) == null ? void 0 : _c2.toFixed(2)) || "N/A") + "% ", 3)
                                ])
                              ]),
                              createVNode("div", { class: "mt-4" }, [
                                createVNode(unref(Link), {
                                  href: route("sentiment-analysis.chart", { coin: coin.coin_id, days: 30 }),
                                  class: "block w-full text-center px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                                }, {
                                  default: withCtx(() => [
                                    createTextVNode(" View Detailed Chart ")
                                  ]),
                                  _: 2
                                }, 1032, ["href"])
                              ])
                            ]);
                          }), 128))
                        ])
                      ])) : (openBlock(), createBlock("div", {
                        key: 1,
                        class: "text-center py-12"
                      }, [
                        (openBlock(), createBlock("svg", {
                          class: "mx-auto h-12 w-12 text-gray-400",
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
                        createVNode("h3", { class: "mt-2 text-sm font-medium text-gray-900" }, "No correlation data available"),
                        createVNode("p", { class: "mt-1 text-sm text-gray-500" }, "Correlation analysis requires sufficient sentiment and price data.")
                      ]))
                    ])
                  ]),
                  createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8" }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-6" }, "Correlation Insights"),
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6" }, [
                        strongestPositiveCorrelation.value ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "bg-green-50 rounded-lg p-4"
                        }, [
                          createVNode("h4", { class: "font-medium text-green-900 mb-2" }, "Strongest Positive Correlation"),
                          createVNode("div", { class: "text-2xl font-bold text-green-700 mb-1" }, toDisplayString(formatCoinName(strongestPositiveCorrelation.value.coin_id)), 1),
                          createVNode("div", { class: "text-green-600" }, toDisplayString(strongestPositiveCorrelation.value.correlation.toFixed(3)) + " correlation coefficient ", 1),
                          createVNode("p", { class: "text-sm text-green-800 mt-2" }, " Sentiment and price movements tend to move in the same direction ")
                        ])) : createCommentVNode("", true),
                        strongestNegativeCorrelation.value ? (openBlock(), createBlock("div", {
                          key: 1,
                          class: "bg-red-50 rounded-lg p-4"
                        }, [
                          createVNode("h4", { class: "font-medium text-red-900 mb-2" }, "Strongest Negative Correlation"),
                          createVNode("div", { class: "text-2xl font-bold text-red-700 mb-1" }, toDisplayString(formatCoinName(strongestNegativeCorrelation.value.coin_id)), 1),
                          createVNode("div", { class: "text-red-600" }, toDisplayString(strongestNegativeCorrelation.value.correlation.toFixed(3)) + " correlation coefficient ", 1),
                          createVNode("p", { class: "text-sm text-red-800 mt-2" }, " Sentiment and price movements tend to move in opposite directions ")
                        ])) : createCommentVNode("", true),
                        weakestCorrelation.value ? (openBlock(), createBlock("div", {
                          key: 2,
                          class: "bg-gray-50 rounded-lg p-4"
                        }, [
                          createVNode("h4", { class: "font-medium text-gray-900 mb-2" }, "Weakest Correlation"),
                          createVNode("div", { class: "text-2xl font-bold text-gray-700 mb-1" }, toDisplayString(formatCoinName(weakestCorrelation.value.coin_id)), 1),
                          createVNode("div", { class: "text-gray-600" }, toDisplayString(weakestCorrelation.value.correlation.toFixed(3)) + " correlation coefficient ", 1),
                          createVNode("p", { class: "text-sm text-gray-800 mt-2" }, " Sentiment appears to have little relationship with price movements ")
                        ])) : createCommentVNode("", true),
                        averageCorrelation.value !== null ? (openBlock(), createBlock("div", {
                          key: 3,
                          class: "bg-blue-50 rounded-lg p-4"
                        }, [
                          createVNode("h4", { class: "font-medium text-blue-900 mb-2" }, "Average Correlation"),
                          createVNode("div", { class: "text-2xl font-bold text-blue-700 mb-1" }, toDisplayString(averageCorrelation.value.toFixed(3)), 1),
                          createVNode("div", { class: "text-blue-600" }, " Across " + toDisplayString(((_h = __props.correlations.coin_correlations) == null ? void 0 : _h.length) || 0) + " cryptocurrencies ", 1),
                          createVNode("p", { class: "text-sm text-blue-800 mt-2" }, toDisplayString(getCorrelationInterpretation(averageCorrelation.value)), 1)
                        ])) : createCommentVNode("", true)
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-amber-50 border border-amber-200 rounded-lg p-6" }, [
                    createVNode("h3", { class: "text-lg font-medium text-amber-900 mb-4" }, "Understanding Correlations"),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6" }, [
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-amber-900 mb-2" }, "Correlation Strength"),
                        createVNode("ul", { class: "text-sm text-amber-800 space-y-1" }, [
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "±0.8 to ±1.0:"),
                            createTextVNode(" Very strong correlation")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "±0.6 to ±0.8:"),
                            createTextVNode(" Strong correlation")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "±0.4 to ±0.6:"),
                            createTextVNode(" Moderate correlation")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "±0.2 to ±0.4:"),
                            createTextVNode(" Weak correlation")
                          ]),
                          createVNode("li", null, [
                            createTextVNode("• "),
                            createVNode("strong", null, "0 to ±0.2:"),
                            createTextVNode(" Very weak/no correlation")
                          ])
                        ])
                      ]),
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-amber-900 mb-2" }, "Important Notes"),
                        createVNode("ul", { class: "text-sm text-amber-800 space-y-1" }, [
                          createVNode("li", null, "• Correlation doesn't imply causation"),
                          createVNode("li", null, "• Market conditions affect correlation strength"),
                          createVNode("li", null, "• External events can disrupt typical patterns"),
                          createVNode("li", null, "• Use multiple timeframes for validation"),
                          createVNode("li", null, "• Consider volume and volatility alongside correlation")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/SentimentAnalysis/Correlations.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
