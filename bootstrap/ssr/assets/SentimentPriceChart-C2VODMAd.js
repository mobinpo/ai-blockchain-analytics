import { mergeProps, ref, computed, onMounted, nextTick, useSSRContext, unref, withCtx, createVNode, createTextVNode, createBlock, openBlock } from "vue";
import { ssrRenderAttrs, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr, ssrInterpolate, ssrRenderClass, ssrRenderStyle, ssrRenderComponent } from "vue/server-renderer";
import { Head, Link } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./AuthenticatedLayout-8TbwyeTu.js";
import axios from "axios";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-1tPrXgE0.js";
import "./ApplicationLogo-B2173abF.js";
const _sfc_main$1 = {
  name: "SentimentPriceChart",
  props: {
    initialCoin: {
      type: String,
      default: "bitcoin"
    },
    initialDays: {
      type: Number,
      default: 30
    }
  },
  setup(props) {
    const loading = ref(false);
    const error = ref(null);
    const chartCanvas = ref(null);
    const chart = ref(null);
    const selectedCoin = ref("");
    const selectedCoinName = ref("");
    const selectedPlatform = ref("all");
    const selectedCategory = ref("all");
    const chartType = ref("dual");
    const startDate = ref("");
    const endDate = ref("");
    const availableCoins = ref([]);
    const chartData = ref(null);
    const statistics = ref(null);
    const quickRanges = [
      { key: "7d", label: "Last 7 Days", days: 7 },
      { key: "30d", label: "Last 30 Days", days: 30 },
      { key: "90d", label: "Last 90 Days", days: 90 },
      { key: "180d", label: "Last 6 Months", days: 180 },
      { key: "365d", label: "Last Year", days: 365 }
    ];
    const hasValidParams = computed(() => {
      return selectedCoin.value && startDate.value && endDate.value;
    });
    const formatDate = (date) => {
      return new Date(date).toISOString().split("T")[0];
    };
    const setQuickRange = (range) => {
      const end = /* @__PURE__ */ new Date();
      const start = new Date(end.getTime() - range.days * 24 * 60 * 60 * 1e3);
      endDate.value = formatDate(end);
      startDate.value = formatDate(start);
      if (hasValidParams.value) {
        fetchChartData();
      }
    };
    const loadAvailableCoins = async () => {
      try {
        const response = await axios.get("/api/sentiment-charts/coins");
        availableCoins.value = response.data.popular_coins || [];
        if (props.initialCoin && availableCoins.value.some((coin) => coin.id === props.initialCoin)) {
          selectedCoin.value = props.initialCoin;
          const coin = availableCoins.value.find((c) => c.id === props.initialCoin);
          selectedCoinName.value = (coin == null ? void 0 : coin.name) || "";
        }
      } catch (err) {
        console.error("Failed to load available coins:", err);
        error.value = "Failed to load available cryptocurrencies";
      }
    };
    const fetchChartData = async () => {
      var _a, _b;
      if (!hasValidParams.value) return;
      loading.value = true;
      error.value = null;
      try {
        const params = {
          coin_id: selectedCoin.value,
          start_date: startDate.value,
          end_date: endDate.value,
          platforms: [selectedPlatform.value],
          categories: [selectedCategory.value],
          include_price: true,
          include_volume: true
        };
        const response = await axios.get("/api/sentiment-charts/data", { params });
        chartData.value = response.data;
        statistics.value = response.data.statistics;
        await nextTick();
        renderChart();
      } catch (err) {
        console.error("Failed to fetch chart data:", err);
        error.value = ((_b = (_a = err.response) == null ? void 0 : _a.data) == null ? void 0 : _b.message) || "Failed to load chart data";
      } finally {
        loading.value = false;
      }
    };
    const renderChart = async () => {
      if (!chartCanvas.value || !chartData.value) return;
      const { Chart, registerables } = await import("chart.js");
      Chart.register(...registerables);
      if (chart.value) {
        chart.value.destroy();
      }
      const ctx = chartCanvas.value.getContext("2d");
      const correlation = chartData.value.correlation_data || [];
      if (chartType.value === "scatter") {
        renderScatterChart(Chart, ctx, correlation);
      } else if (chartType.value === "dual") {
        renderDualAxisChart(Chart, ctx, correlation);
      } else {
        renderLineChart(Chart, ctx, correlation);
      }
    };
    const renderLineChart = (Chart, ctx, data) => {
      chart.value = new Chart(ctx, {
        type: "line",
        data: {
          labels: data.map((d) => new Date(d.date).toLocaleDateString()),
          datasets: [
            {
              label: "Sentiment Score",
              data: data.map((d) => d.sentiment),
              borderColor: "rgb(59, 130, 246)",
              backgroundColor: "rgba(59, 130, 246, 0.1)",
              yAxisID: "y"
            },
            {
              label: "Price Change %",
              data: data.map((d) => d.price_change),
              borderColor: "rgb(16, 185, 129)",
              backgroundColor: "rgba(16, 185, 129, 0.1)",
              yAxisID: "y1"
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: "index",
            intersect: false
          },
          scales: {
            x: {
              display: true,
              title: {
                display: true,
                text: "Date"
              }
            },
            y: {
              type: "linear",
              display: true,
              position: "left",
              title: {
                display: true,
                text: "Sentiment Score"
              }
            },
            y1: {
              type: "linear",
              display: true,
              position: "right",
              title: {
                display: true,
                text: "Price Change %"
              },
              grid: {
                drawOnChartArea: false
              }
            }
          }
        }
      });
    };
    const renderScatterChart = (Chart, ctx, data) => {
      chart.value = new Chart(ctx, {
        type: "scatter",
        data: {
          datasets: [{
            label: "Sentiment vs Price Change",
            data: data.map((d) => ({
              x: d.sentiment,
              y: d.price_change,
              r: Math.max(3, Math.min(15, d.posts / 10))
              // Bubble size based on post count
            })),
            backgroundColor: "rgba(59, 130, 246, 0.6)",
            borderColor: "rgb(59, 130, 246)"
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: {
              title: {
                display: true,
                text: "Sentiment Score"
              }
            },
            y: {
              title: {
                display: true,
                text: "Price Change %"
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: (context) => {
                  const point = data[context.dataIndex];
                  return [
                    `Date: ${new Date(point.date).toLocaleDateString()}`,
                    `Sentiment: ${point.sentiment.toFixed(3)}`,
                    `Price Change: ${point.price_change.toFixed(2)}%`,
                    `Posts: ${point.posts}`
                  ];
                }
              }
            }
          }
        }
      });
    };
    const renderDualAxisChart = (Chart, ctx, data) => {
      chart.value = new Chart(ctx, {
        type: "line",
        data: {
          labels: data.map((d) => new Date(d.date).toLocaleDateString()),
          datasets: [
            {
              label: "Sentiment Score",
              data: data.map((d) => d.sentiment),
              borderColor: "rgb(59, 130, 246)",
              backgroundColor: "rgba(59, 130, 246, 0.1)",
              yAxisID: "y",
              tension: 0.3
            },
            {
              label: "Price (Normalized)",
              data: data.map((d) => d.price),
              borderColor: "rgb(16, 185, 129)",
              backgroundColor: "rgba(16, 185, 129, 0.1)",
              yAxisID: "y1",
              tension: 0.3
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: "index",
            intersect: false
          },
          scales: {
            x: {
              display: true,
              title: {
                display: true,
                text: "Date"
              }
            },
            y: {
              type: "linear",
              display: true,
              position: "left",
              title: {
                display: true,
                text: "Sentiment Score"
              },
              min: -1,
              max: 1
            },
            y1: {
              type: "linear",
              display: true,
              position: "right",
              title: {
                display: true,
                text: "Price (USD)"
              },
              grid: {
                drawOnChartArea: false
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                afterBody: (tooltipItems) => {
                  var _a;
                  const index = tooltipItems[0].dataIndex;
                  const point = data[index];
                  return [
                    "",
                    `Posts: ${point.posts}`,
                    `Volatility: ${((_a = point.volatility) == null ? void 0 : _a.toFixed(3)) || "N/A"}`
                  ];
                }
              }
            }
          }
        }
      });
    };
    const updateChart = () => {
      if (chartData.value) {
        renderChart();
      }
    };
    const onCoinChange = () => {
      const coin = availableCoins.value.find((c) => c.id === selectedCoin.value);
      selectedCoinName.value = (coin == null ? void 0 : coin.name) || "";
      if (hasValidParams.value) {
        fetchChartData();
      }
    };
    const onDateChange = () => {
      if (hasValidParams.value) {
        fetchChartData();
      }
    };
    const onFiltersChange = () => {
      if (hasValidParams.value) {
        fetchChartData();
      }
    };
    const getCorrelationColor = (correlation) => {
      if (!correlation) return "text-gray-500";
      const abs = Math.abs(correlation);
      if (abs >= 0.6) return correlation > 0 ? "text-green-600" : "text-red-600";
      if (abs >= 0.3) return correlation > 0 ? "text-green-500" : "text-red-500";
      return "text-gray-500";
    };
    const getSentimentColor = (sentiment) => {
      if (!sentiment) return "text-gray-500";
      if (sentiment > 0.2) return "text-green-600";
      if (sentiment < -0.2) return "text-red-600";
      return "text-gray-500";
    };
    const getPriceChangeColor = (change) => {
      if (!change) return "text-gray-500";
      return change > 0 ? "text-green-600" : "text-red-600";
    };
    const getSentimentLabel = (sentiment) => {
      if (!sentiment) return "";
      if (sentiment > 0.6) return "Very Positive";
      if (sentiment > 0.2) return "Positive";
      if (sentiment > -0.2) return "Neutral";
      if (sentiment > -0.6) return "Negative";
      return "Very Negative";
    };
    onMounted(async () => {
      await loadAvailableCoins();
      setQuickRange(quickRanges.find((r) => r.days === props.initialDays) || quickRanges[1]);
    });
    return {
      // State
      loading,
      error,
      chartCanvas,
      selectedCoin,
      selectedCoinName,
      selectedPlatform,
      selectedCategory,
      chartType,
      startDate,
      endDate,
      availableCoins,
      chartData,
      statistics,
      quickRanges,
      // Methods
      setQuickRange,
      onCoinChange,
      onDateChange,
      onFiltersChange,
      updateChart,
      getCorrelationColor,
      getSentimentColor,
      getPriceChangeColor,
      getSentimentLabel
    };
  }
};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  var _a, _b, _c, _d, _e, _f, _g, _h;
  _push(`<div${ssrRenderAttrs(mergeProps({ class: "sentiment-price-chart-container" }, _attrs))} data-v-9da36102><div class="chart-controls mb-6 p-4 bg-gray-50 rounded-lg" data-v-9da36102><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" data-v-9da36102><div data-v-9da36102><label class="block text-sm font-medium text-gray-700 mb-2" data-v-9da36102> Cryptocurrency </label><select class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" data-v-9da36102><option value="" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.selectedCoin) ? ssrLooseContain($setup.selectedCoin, "") : ssrLooseEqual($setup.selectedCoin, "")) ? " selected" : ""}>Select a coin...</option><!--[-->`);
  ssrRenderList($setup.availableCoins, (coin) => {
    _push(`<option${ssrRenderAttr("value", coin.id)} data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.selectedCoin) ? ssrLooseContain($setup.selectedCoin, coin.id) : ssrLooseEqual($setup.selectedCoin, coin.id)) ? " selected" : ""}>${ssrInterpolate(coin.name)} (${ssrInterpolate(coin.symbol.toUpperCase())}) </option>`);
  });
  _push(`<!--]--></select></div><div data-v-9da36102><label class="block text-sm font-medium text-gray-700 mb-2" data-v-9da36102> Start Date </label><input${ssrRenderAttr("value", $setup.startDate)} type="date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" data-v-9da36102></div><div data-v-9da36102><label class="block text-sm font-medium text-gray-700 mb-2" data-v-9da36102> End Date </label><input${ssrRenderAttr("value", $setup.endDate)} type="date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" data-v-9da36102></div><div data-v-9da36102><label class="block text-sm font-medium text-gray-700 mb-2" data-v-9da36102> Platform </label><select class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" data-v-9da36102><option value="all" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.selectedPlatform) ? ssrLooseContain($setup.selectedPlatform, "all") : ssrLooseEqual($setup.selectedPlatform, "all")) ? " selected" : ""}>All Platforms</option><option value="twitter" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.selectedPlatform) ? ssrLooseContain($setup.selectedPlatform, "twitter") : ssrLooseEqual($setup.selectedPlatform, "twitter")) ? " selected" : ""}>Twitter</option><option value="reddit" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.selectedPlatform) ? ssrLooseContain($setup.selectedPlatform, "reddit") : ssrLooseEqual($setup.selectedPlatform, "reddit")) ? " selected" : ""}>Reddit</option><option value="telegram" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.selectedPlatform) ? ssrLooseContain($setup.selectedPlatform, "telegram") : ssrLooseEqual($setup.selectedPlatform, "telegram")) ? " selected" : ""}>Telegram</option></select></div></div><div class="mt-4 flex flex-wrap gap-2" data-v-9da36102><!--[-->`);
  ssrRenderList($setup.quickRanges, (range) => {
    _push(`<button class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500" data-v-9da36102>${ssrInterpolate(range.label)}</button>`);
  });
  _push(`<!--]--></div></div>`);
  if ($setup.loading) {
    _push(`<div class="flex justify-center items-center h-64" data-v-9da36102><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" data-v-9da36102></div><span class="ml-2 text-gray-600" data-v-9da36102>Loading chart data...</span></div>`);
  } else if ($setup.error) {
    _push(`<div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6" data-v-9da36102><div class="flex" data-v-9da36102><div class="flex-shrink-0" data-v-9da36102><svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" data-v-9da36102><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" data-v-9da36102></path></svg></div><div class="ml-3" data-v-9da36102><h3 class="text-sm font-medium text-red-800" data-v-9da36102>Error loading chart data</h3><p class="mt-1 text-sm text-red-700" data-v-9da36102>${ssrInterpolate($setup.error)}</p></div></div></div>`);
  } else if ($setup.statistics) {
    _push(`<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" data-v-9da36102><div class="bg-white p-4 rounded-lg shadow" data-v-9da36102><div class="text-sm font-medium text-gray-500" data-v-9da36102>Correlation</div><div class="${ssrRenderClass([$setup.getCorrelationColor($setup.statistics.correlation_coefficient), "mt-1 text-2xl font-semibold"])}" data-v-9da36102>${ssrInterpolate(((_a = $setup.statistics.correlation_coefficient) == null ? void 0 : _a.toFixed(3)) || "N/A")}</div><div class="text-xs text-gray-600" data-v-9da36102>${ssrInterpolate($setup.statistics.correlation_strength || "")}</div></div><div class="bg-white p-4 rounded-lg shadow" data-v-9da36102><div class="text-sm font-medium text-gray-500" data-v-9da36102>Avg Sentiment</div><div class="${ssrRenderClass([$setup.getSentimentColor((_b = $setup.statistics.sentiment_stats) == null ? void 0 : _b.average), "mt-1 text-2xl font-semibold"])}" data-v-9da36102>${ssrInterpolate(((_d = (_c = $setup.statistics.sentiment_stats) == null ? void 0 : _c.average) == null ? void 0 : _d.toFixed(3)) || "N/A")}</div><div class="text-xs text-gray-600" data-v-9da36102>${ssrInterpolate($setup.getSentimentLabel((_e = $setup.statistics.sentiment_stats) == null ? void 0 : _e.average))}</div></div><div class="bg-white p-4 rounded-lg shadow" data-v-9da36102><div class="text-sm font-medium text-gray-500" data-v-9da36102>Avg Price Change</div><div class="${ssrRenderClass([$setup.getPriceChangeColor((_f = $setup.statistics.price_stats) == null ? void 0 : _f.average_change), "mt-1 text-2xl font-semibold"])}" data-v-9da36102>${ssrInterpolate(((_h = (_g = $setup.statistics.price_stats) == null ? void 0 : _g.average_change) == null ? void 0 : _h.toFixed(2)) || "N/A")}% </div><div class="text-xs text-gray-600" data-v-9da36102>Daily average</div></div><div class="bg-white p-4 rounded-lg shadow" data-v-9da36102><div class="text-sm font-medium text-gray-500" data-v-9da36102>Data Points</div><div class="mt-1 text-2xl font-semibold text-gray-900" data-v-9da36102>${ssrInterpolate($setup.statistics.data_points || 0)}</div><div class="text-xs text-gray-600" data-v-9da36102>Days analyzed</div></div></div>`);
  } else if ($setup.chartData) {
    _push(`<div class="bg-white rounded-lg shadow-lg p-6" data-v-9da36102><div class="mb-4 flex justify-between items-center" data-v-9da36102><h3 class="text-lg font-medium text-gray-900" data-v-9da36102> Sentiment vs Price Timeline `);
    if ($setup.selectedCoinName) {
      _push(`<span class="text-sm text-gray-500 ml-2" data-v-9da36102> (${ssrInterpolate($setup.selectedCoinName)}) </span>`);
    } else {
      _push(`<!---->`);
    }
    _push(`</h3><div class="flex items-center space-x-2" data-v-9da36102><label class="text-sm text-gray-700" data-v-9da36102>Chart Type:</label><select class="text-sm rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" data-v-9da36102><option value="line" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.chartType) ? ssrLooseContain($setup.chartType, "line") : ssrLooseEqual($setup.chartType, "line")) ? " selected" : ""}>Line Chart</option><option value="scatter" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.chartType) ? ssrLooseContain($setup.chartType, "scatter") : ssrLooseEqual($setup.chartType, "scatter")) ? " selected" : ""}>Scatter Plot</option><option value="dual" data-v-9da36102${ssrIncludeBooleanAttr(Array.isArray($setup.chartType) ? ssrLooseContain($setup.chartType, "dual") : ssrLooseEqual($setup.chartType, "dual")) ? " selected" : ""}>Dual Axis</option></select></div></div><div class="relative" style="${ssrRenderStyle({ "height": "400px" })}" data-v-9da36102><canvas data-v-9da36102></canvas></div><div class="mt-4 text-sm text-gray-600" data-v-9da36102><div class="flex flex-wrap gap-4" data-v-9da36102><div class="flex items-center" data-v-9da36102><div class="w-3 h-3 bg-blue-500 rounded mr-2" data-v-9da36102></div><span data-v-9da36102>Sentiment Score</span></div><div class="flex items-center" data-v-9da36102><div class="w-3 h-3 bg-green-500 rounded mr-2" data-v-9da36102></div><span data-v-9da36102>Price (USD)</span></div>`);
    if ($setup.chartType === "scatter") {
      _push(`<div class="text-xs" data-v-9da36102><span data-v-9da36102>Bubble size represents post volume</span></div>`);
    } else {
      _push(`<!---->`);
    }
    _push(`</div></div></div>`);
  } else {
    _push(`<div class="text-center py-12" data-v-9da36102><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-9da36102><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" data-v-9da36102></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900" data-v-9da36102>No data available</h3><p class="mt-1 text-sm text-gray-500" data-v-9da36102>Select a cryptocurrency and date range to view the chart.</p></div>`);
  }
  _push(`</div>`);
}
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Charts/SentimentPriceChart.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const SentimentPriceChart$1 = /* @__PURE__ */ _export_sfc(_sfc_main$1, [["ssrRender", _sfc_ssrRender], ["__scopeId", "data-v-9da36102"]]);
const _sfc_main = {
  __name: "SentimentPriceChart",
  __ssrInlineRender: true,
  props: {
    initialCoin: {
      type: String,
      default: "bitcoin"
    },
    initialDays: {
      type: Number,
      default: 30
    }
  },
  setup(__props) {
    const chartComponent = ref(null);
    const exportChartData = () => {
      console.log("Export chart data");
    };
    const route = (name, params = {}) => {
      const routes = {
        "sentiment-analysis.index": "/sentiment-analysis",
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
      _push(ssrRenderComponent(unref(Head), { title: "Sentiment vs Price Analysis" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$2, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex justify-between items-center" data-v-e912ed18${_scopeId}><div data-v-e912ed18${_scopeId}><h2 class="font-semibold text-xl text-gray-800 leading-tight" data-v-e912ed18${_scopeId}> Sentiment vs Price Analysis </h2><p class="mt-1 text-sm text-gray-600" data-v-e912ed18${_scopeId}> Analyze the correlation between social sentiment and cryptocurrency prices </p></div><div class="flex items-center space-x-3" data-v-e912ed18${_scopeId}><button class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-v-e912ed18${_scopeId}><svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-e912ed18${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-e912ed18${_scopeId}></path></svg> Export Data </button>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.index"),
              class: "inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-e912ed18${_scopeId2}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" data-v-e912ed18${_scopeId2}></path></svg> Dashboard `);
                } else {
                  return [
                    (openBlock(), createBlock("svg", {
                      class: "-ml-0.5 mr-2 h-4 w-4",
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
                    createTextVNode(" Dashboard ")
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
                  createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, " Sentiment vs Price Analysis "),
                  createVNode("p", { class: "mt-1 text-sm text-gray-600" }, " Analyze the correlation between social sentiment and cryptocurrency prices ")
                ]),
                createVNode("div", { class: "flex items-center space-x-3" }, [
                  createVNode("button", {
                    onClick: exportChartData,
                    class: "inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  }, [
                    (openBlock(), createBlock("svg", {
                      class: "-ml-0.5 mr-2 h-4 w-4",
                      fill: "none",
                      viewBox: "0 0 24 24",
                      stroke: "currentColor"
                    }, [
                      createVNode("path", {
                        "stroke-linecap": "round",
                        "stroke-linejoin": "round",
                        "stroke-width": "2",
                        d: "M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                      })
                    ])),
                    createTextVNode(" Export Data ")
                  ]),
                  createVNode(unref(Link), {
                    href: route("sentiment-analysis.index"),
                    class: "inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  }, {
                    default: withCtx(() => [
                      (openBlock(), createBlock("svg", {
                        class: "-ml-0.5 mr-2 h-4 w-4",
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
                      createTextVNode(" Dashboard ")
                    ]),
                    _: 1
                  }, 8, ["href"])
                ])
              ])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="py-12" data-v-e912ed18${_scopeId}><div class="max-w-8xl mx-auto sm:px-6 lg:px-8" data-v-e912ed18${_scopeId}>`);
            _push2(ssrRenderComponent(SentimentPriceChart$1, {
              "initial-coin": __props.initialCoin,
              "initial-days": __props.initialDays,
              ref_key: "chartComponent",
              ref: chartComponent
            }, null, _parent2, _scopeId));
            _push2(`<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6" data-v-e912ed18${_scopeId}><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-v-e912ed18${_scopeId}><div class="p-6" data-v-e912ed18${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-4" data-v-e912ed18${_scopeId}> Correlation Insights </h3><div class="space-y-4" data-v-e912ed18${_scopeId}><div class="bg-blue-50 rounded-lg p-4" data-v-e912ed18${_scopeId}><h4 class="text-sm font-medium text-blue-900 mb-2" data-v-e912ed18${_scopeId}> How to Interpret Correlation </h4><ul class="text-sm text-blue-800 space-y-1" data-v-e912ed18${_scopeId}><li data-v-e912ed18${_scopeId}>• <strong data-v-e912ed18${_scopeId}>+0.6 to +1.0:</strong> Strong positive correlation</li><li data-v-e912ed18${_scopeId}>• <strong data-v-e912ed18${_scopeId}>+0.3 to +0.6:</strong> Moderate positive correlation</li><li data-v-e912ed18${_scopeId}>• <strong data-v-e912ed18${_scopeId}>-0.3 to +0.3:</strong> Weak or no correlation</li><li data-v-e912ed18${_scopeId}>• <strong data-v-e912ed18${_scopeId}>-0.6 to -0.3:</strong> Moderate negative correlation</li><li data-v-e912ed18${_scopeId}>• <strong data-v-e912ed18${_scopeId}>-1.0 to -0.6:</strong> Strong negative correlation</li></ul></div><div class="bg-amber-50 rounded-lg p-4" data-v-e912ed18${_scopeId}><h4 class="text-sm font-medium text-amber-900 mb-2" data-v-e912ed18${_scopeId}> Analysis Tips </h4><ul class="text-sm text-amber-800 space-y-1" data-v-e912ed18${_scopeId}><li data-v-e912ed18${_scopeId}>• Correlation doesn&#39;t imply causation</li><li data-v-e912ed18${_scopeId}>• Consider external market factors</li><li data-v-e912ed18${_scopeId}>• Use multiple timeframes for confirmation</li><li data-v-e912ed18${_scopeId}>• Monitor sentiment volume and volatility</li></ul></div></div></div></div><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-v-e912ed18${_scopeId}><div class="p-6" data-v-e912ed18${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-4" data-v-e912ed18${_scopeId}> Quick Actions </h3><div class="space-y-3" data-v-e912ed18${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "twitter", days: 30 }),
              class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium text-gray-900" data-v-e912ed18${_scopeId2}>Twitter Sentiment Analysis</div><div class="text-sm text-gray-500" data-v-e912ed18${_scopeId2}>Deep dive into Twitter sentiment trends</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium text-gray-900" }, "Twitter Sentiment Analysis"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Deep dive into Twitter sentiment trends")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.platform", { platform: "reddit", days: 30 }),
              class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium text-gray-900" data-v-e912ed18${_scopeId2}>Reddit Sentiment Analysis</div><div class="text-sm text-gray-500" data-v-e912ed18${_scopeId2}>Analyze Reddit discussion sentiment</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium text-gray-900" }, "Reddit Sentiment Analysis"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Analyze Reddit discussion sentiment")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.trends", { timeframe: "90d" }),
              class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium text-gray-900" data-v-e912ed18${_scopeId2}>Long-term Trends</div><div class="text-sm text-gray-500" data-v-e912ed18${_scopeId2}>View 90-day sentiment trends</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium text-gray-900" }, "Long-term Trends"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "View 90-day sentiment trends")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: route("sentiment-analysis.correlations"),
              class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="font-medium text-gray-900" data-v-e912ed18${_scopeId2}>Multi-Coin Correlations</div><div class="text-sm text-gray-500" data-v-e912ed18${_scopeId2}>Compare multiple cryptocurrencies</div>`);
                } else {
                  return [
                    createVNode("div", { class: "font-medium text-gray-900" }, "Multi-Coin Correlations"),
                    createVNode("div", { class: "text-sm text-gray-500" }, "Compare multiple cryptocurrencies")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></div></div></div><div class="mt-8 bg-gray-50 rounded-lg p-6" data-v-e912ed18${_scopeId}><h3 class="text-lg font-medium text-gray-900 mb-4" data-v-e912ed18${_scopeId}> Understanding Sentiment vs Price Analysis </h3><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" data-v-e912ed18${_scopeId}><div data-v-e912ed18${_scopeId}><h4 class="font-medium text-gray-900 mb-2" data-v-e912ed18${_scopeId}>Sentiment Score</h4><p class="text-sm text-gray-600" data-v-e912ed18${_scopeId}> Ranges from -1 (very negative) to +1 (very positive). Calculated using Google Cloud Natural Language API on social media posts and news articles. </p></div><div data-v-e912ed18${_scopeId}><h4 class="font-medium text-gray-900 mb-2" data-v-e912ed18${_scopeId}>Price Data</h4><p class="text-sm text-gray-600" data-v-e912ed18${_scopeId}> Real-time cryptocurrency prices from CoinGecko API. Shows daily price changes as percentages for better correlation analysis. </p></div><div data-v-e912ed18${_scopeId}><h4 class="font-medium text-gray-900 mb-2" data-v-e912ed18${_scopeId}>Chart Types</h4><p class="text-sm text-gray-600" data-v-e912ed18${_scopeId}> Line chart shows trends over time, scatter plot reveals correlation patterns, and dual-axis allows direct comparison of sentiment and price movements. </p></div></div></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "max-w-8xl mx-auto sm:px-6 lg:px-8" }, [
                  createVNode(SentimentPriceChart$1, {
                    "initial-coin": __props.initialCoin,
                    "initial-days": __props.initialDays,
                    ref_key: "chartComponent",
                    ref: chartComponent
                  }, null, 8, ["initial-coin", "initial-days"]),
                  createVNode("div", { class: "mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6" }, [
                    createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg" }, [
                      createVNode("div", { class: "p-6" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-4" }, " Correlation Insights "),
                        createVNode("div", { class: "space-y-4" }, [
                          createVNode("div", { class: "bg-blue-50 rounded-lg p-4" }, [
                            createVNode("h4", { class: "text-sm font-medium text-blue-900 mb-2" }, " How to Interpret Correlation "),
                            createVNode("ul", { class: "text-sm text-blue-800 space-y-1" }, [
                              createVNode("li", null, [
                                createTextVNode("• "),
                                createVNode("strong", null, "+0.6 to +1.0:"),
                                createTextVNode(" Strong positive correlation")
                              ]),
                              createVNode("li", null, [
                                createTextVNode("• "),
                                createVNode("strong", null, "+0.3 to +0.6:"),
                                createTextVNode(" Moderate positive correlation")
                              ]),
                              createVNode("li", null, [
                                createTextVNode("• "),
                                createVNode("strong", null, "-0.3 to +0.3:"),
                                createTextVNode(" Weak or no correlation")
                              ]),
                              createVNode("li", null, [
                                createTextVNode("• "),
                                createVNode("strong", null, "-0.6 to -0.3:"),
                                createTextVNode(" Moderate negative correlation")
                              ]),
                              createVNode("li", null, [
                                createTextVNode("• "),
                                createVNode("strong", null, "-1.0 to -0.6:"),
                                createTextVNode(" Strong negative correlation")
                              ])
                            ])
                          ]),
                          createVNode("div", { class: "bg-amber-50 rounded-lg p-4" }, [
                            createVNode("h4", { class: "text-sm font-medium text-amber-900 mb-2" }, " Analysis Tips "),
                            createVNode("ul", { class: "text-sm text-amber-800 space-y-1" }, [
                              createVNode("li", null, "• Correlation doesn't imply causation"),
                              createVNode("li", null, "• Consider external market factors"),
                              createVNode("li", null, "• Use multiple timeframes for confirmation"),
                              createVNode("li", null, "• Monitor sentiment volume and volatility")
                            ])
                          ])
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "bg-white overflow-hidden shadow-sm sm:rounded-lg" }, [
                      createVNode("div", { class: "p-6" }, [
                        createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-4" }, " Quick Actions "),
                        createVNode("div", { class: "space-y-3" }, [
                          createVNode(unref(Link), {
                            href: route("sentiment-analysis.platform", { platform: "twitter", days: 30 }),
                            class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                          }, {
                            default: withCtx(() => [
                              createVNode("div", { class: "font-medium text-gray-900" }, "Twitter Sentiment Analysis"),
                              createVNode("div", { class: "text-sm text-gray-500" }, "Deep dive into Twitter sentiment trends")
                            ]),
                            _: 1
                          }, 8, ["href"]),
                          createVNode(unref(Link), {
                            href: route("sentiment-analysis.platform", { platform: "reddit", days: 30 }),
                            class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                          }, {
                            default: withCtx(() => [
                              createVNode("div", { class: "font-medium text-gray-900" }, "Reddit Sentiment Analysis"),
                              createVNode("div", { class: "text-sm text-gray-500" }, "Analyze Reddit discussion sentiment")
                            ]),
                            _: 1
                          }, 8, ["href"]),
                          createVNode(unref(Link), {
                            href: route("sentiment-analysis.trends", { timeframe: "90d" }),
                            class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                          }, {
                            default: withCtx(() => [
                              createVNode("div", { class: "font-medium text-gray-900" }, "Long-term Trends"),
                              createVNode("div", { class: "text-sm text-gray-500" }, "View 90-day sentiment trends")
                            ]),
                            _: 1
                          }, 8, ["href"]),
                          createVNode(unref(Link), {
                            href: route("sentiment-analysis.correlations"),
                            class: "block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                          }, {
                            default: withCtx(() => [
                              createVNode("div", { class: "font-medium text-gray-900" }, "Multi-Coin Correlations"),
                              createVNode("div", { class: "text-sm text-gray-500" }, "Compare multiple cryptocurrencies")
                            ]),
                            _: 1
                          }, 8, ["href"])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "mt-8 bg-gray-50 rounded-lg p-6" }, [
                    createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-4" }, " Understanding Sentiment vs Price Analysis "),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" }, [
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-gray-900 mb-2" }, "Sentiment Score"),
                        createVNode("p", { class: "text-sm text-gray-600" }, " Ranges from -1 (very negative) to +1 (very positive). Calculated using Google Cloud Natural Language API on social media posts and news articles. ")
                      ]),
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-gray-900 mb-2" }, "Price Data"),
                        createVNode("p", { class: "text-sm text-gray-600" }, " Real-time cryptocurrency prices from CoinGecko API. Shows daily price changes as percentages for better correlation analysis. ")
                      ]),
                      createVNode("div", null, [
                        createVNode("h4", { class: "font-medium text-gray-900 mb-2" }, "Chart Types"),
                        createVNode("p", { class: "text-sm text-gray-600" }, " Line chart shows trends over time, scatter plot reveals correlation patterns, and dual-axis allows direct comparison of sentiment and price movements. ")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/SentimentAnalysis/SentimentPriceChart.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const SentimentPriceChart = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-e912ed18"]]);
export {
  SentimentPriceChart as default
};
