import { ref, computed, mergeProps, useSSRContext, onMounted, onUnmounted, unref, withCtx, createVNode, resolveDynamicComponent, toDisplayString, createBlock, openBlock, Fragment, renderList, createTextVNode } from "vue";
import { ssrRenderAttrs, ssrRenderList, ssrRenderClass, ssrInterpolate, ssrRenderAttr, ssrRenderStyle, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderComponent, ssrRenderVNode } from "vue/server-renderer";
import { _ as _sfc_main$7 } from "./AuthenticatedLayout-8TbwyeTu.js";
import { Head } from "@inertiajs/vue3";
import { ChartBarIcon, CpuChipIcon, ExclamationTriangleIcon, HeartIcon, ArrowTrendingUpIcon, CheckCircleIcon, EyeIcon, ShieldCheckIcon, ArrowTrendingDownIcon, MinusIcon } from "@heroicons/vue/24/outline";
import "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const _sfc_main$6 = {
  __name: "SecurityChart",
  __ssrInlineRender: true,
  setup(__props) {
    const selectedPeriod = ref("7D");
    const periods = ["24H", "7D", "30D", "90D"];
    const showTooltip = ref(null);
    const criticalData = [
      { x: 50, y: 150, value: 2 },
      { x: 100, y: 120, value: 4 },
      { x: 150, y: 100, value: 6 },
      { x: 200, y: 80, value: 3 },
      { x: 250, y: 90, value: 1 },
      { x: 300, y: 110, value: 2 },
      { x: 350, y: 95, value: 0 }
    ];
    const highData = [
      { x: 50, y: 140, value: 5 },
      { x: 100, y: 110, value: 8 },
      { x: 150, y: 90, value: 12 },
      { x: 200, y: 70, value: 7 },
      { x: 250, y: 80, value: 4 },
      { x: 300, y: 100, value: 6 },
      { x: 350, y: 85, value: 3 }
    ];
    const mediumData = [
      { x: 50, y: 130, value: 8 },
      { x: 100, y: 100, value: 15 },
      { x: 150, y: 80, value: 20 },
      { x: 200, y: 60, value: 12 },
      { x: 250, y: 70, value: 9 },
      { x: 300, y: 90, value: 11 },
      { x: 350, y: 75, value: 7 }
    ];
    const criticalPoints = computed(
      () => criticalData.map((point) => `${point.x},${point.y}`).join(" ")
    );
    const highPoints = computed(
      () => highData.map((point) => `${point.x},${point.y}`).join(" ")
    );
    const mediumPoints = computed(
      () => mediumData.map((point) => `${point.x},${point.y}`).join(" ")
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, _attrs))}><div class="flex items-center justify-between mb-4"><h3 class="text-lg font-semibold text-gray-900">Security Findings Trend</h3><div class="flex space-x-2"><!--[-->`);
      ssrRenderList(periods, (period) => {
        _push(`<button class="${ssrRenderClass([
          "px-3 py-1 text-xs font-medium rounded-md transition-colors",
          selectedPeriod.value === period ? "bg-indigo-100 text-indigo-700" : "text-gray-500 hover:text-gray-700"
        ])}">${ssrInterpolate(period)}</button>`);
      });
      _push(`<!--]--></div></div><div class="relative h-64"><svg class="w-full h-full" viewBox="0 0 400 200"><defs><pattern id="grid" width="40" height="20" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 20" fill="none" stroke="#f3f4f6" stroke-width="1"></path></pattern></defs><rect width="100%" height="100%" fill="url(#grid)"></rect><polyline${ssrRenderAttr("points", criticalPoints.value)} fill="none" stroke="#dc2626" stroke-width="3" class="drop-shadow-sm"></polyline><polyline${ssrRenderAttr("points", highPoints.value)} fill="none" stroke="#ea580c" stroke-width="2" class="drop-shadow-sm"></polyline><polyline${ssrRenderAttr("points", mediumPoints.value)} fill="none" stroke="#d97706" stroke-width="2" class="drop-shadow-sm"></polyline><!--[-->`);
      ssrRenderList(criticalData, (point, index) => {
        _push(`<g><circle${ssrRenderAttr("cx", point.x)}${ssrRenderAttr("cy", point.y)} r="4" fill="#dc2626" class="hover:r-6 transition-all cursor-pointer"></circle></g>`);
      });
      _push(`<!--]--></svg>`);
      if (showTooltip.value) {
        _push(`<div style="${ssrRenderStyle({ left: showTooltip.value.x + "px", top: showTooltip.value.y - 40 + "px" })}" class="absolute bg-gray-900 text-white text-xs px-2 py-1 rounded shadow-lg pointer-events-none z-10">${ssrInterpolate(showTooltip.value.type)}: ${ssrInterpolate(showTooltip.value.value)}</div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="flex items-center justify-center space-x-6 mt-4"><div class="flex items-center"><div class="w-3 h-3 bg-red-600 rounded-full mr-2"></div><span class="text-sm text-gray-600">Critical</span></div><div class="flex items-center"><div class="w-3 h-3 bg-orange-600 rounded-full mr-2"></div><span class="text-sm text-gray-600">High</span></div><div class="flex items-center"><div class="w-3 h-3 bg-yellow-600 rounded-full mr-2"></div><span class="text-sm text-gray-600">Medium</span></div></div></div>`);
    };
  }
};
const _sfc_setup$6 = _sfc_main$6.setup;
_sfc_main$6.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Analytics/SecurityChart.vue");
  return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
const _sfc_main$5 = {
  __name: "SentimentGauge",
  __ssrInlineRender: true,
  props: {
    sentiment: {
      type: Number,
      default: 0.72
    },
    projectCount: {
      type: Number,
      default: 47
    },
    analysisCount: {
      type: Number,
      default: 156
    }
  },
  setup(__props) {
    const props = __props;
    const needleAngle = computed(() => {
      return (props.sentiment - 0.5) * 180;
    });
    const sentimentArc = computed(() => {
      const startAngle = -90;
      const endAngle = needleAngle.value;
      const radius = 80;
      const centerX = 100;
      const centerY = 80;
      const startRadians = startAngle * Math.PI / 180;
      const endRadians = endAngle * Math.PI / 180;
      const startX = centerX + radius * Math.cos(startRadians);
      const startY = centerY + radius * Math.sin(startRadians);
      const endX = centerX + radius * Math.cos(endRadians);
      const endY = centerY + radius * Math.sin(endRadians);
      const largeArcFlag = endAngle - startAngle > 180 ? 1 : 0;
      return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`;
    });
    const sentimentColor = computed(() => {
      if (props.sentiment >= 0.7) return "#10b981";
      if (props.sentiment >= 0.5) return "#f59e0b";
      return "#ef4444";
    });
    const sentimentIndicatorColor = computed(() => {
      if (props.sentiment >= 0.7) return "bg-green-500";
      if (props.sentiment >= 0.5) return "bg-yellow-500";
      return "bg-red-500";
    });
    const sentimentTextColor = computed(() => {
      if (props.sentiment >= 0.7) return "text-green-600";
      if (props.sentiment >= 0.5) return "text-yellow-600";
      return "text-red-600";
    });
    const sentimentLabel = computed(() => {
      if (props.sentiment >= 0.8) return "Very Positive";
      if (props.sentiment >= 0.6) return "Positive";
      if (props.sentiment >= 0.4) return "Neutral";
      if (props.sentiment >= 0.2) return "Negative";
      return "Very Negative";
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, _attrs))}><div class="text-center"><h3 class="text-lg font-semibold text-gray-900 mb-2">Community Sentiment</h3><p class="text-sm text-gray-600 mb-4">Real-time sentiment analysis across all projects</p><div class="relative w-48 h-24 mx-auto mb-4"><svg class="w-full h-full" viewBox="0 0 200 100"><path d="M 20 80 A 80 80 0 0 1 180 80" fill="none" stroke="#f3f4f6" stroke-width="8" stroke-linecap="round"></path><path${ssrRenderAttr("d", sentimentArc.value)} fill="none"${ssrRenderAttr("stroke", sentimentColor.value)} stroke-width="8" stroke-linecap="round" class="transition-all duration-1000 ease-out"></path><g${ssrRenderAttr("transform", `rotate(${needleAngle.value} 100 80)`)}><line x1="100" y1="80" x2="100" y2="25" stroke="#374151" stroke-width="2" stroke-linecap="round"></line><circle cx="100" cy="80" r="4" fill="#374151"></circle></g><text x="100" y="95" text-anchor="middle" class="text-xl font-bold fill-gray-900">${ssrInterpolate(Math.round(__props.sentiment * 100))}% </text></svg><div class="absolute bottom-0 left-0 text-xs text-gray-500">Negative</div><div class="absolute bottom-0 right-0 text-xs text-gray-500">Positive</div></div><div class="flex items-center justify-center space-x-2 mb-4"><div class="${ssrRenderClass(["w-2 h-2 rounded-full", sentimentIndicatorColor.value])}"></div><span class="${ssrRenderClass(["text-sm font-medium", sentimentTextColor.value])}">${ssrInterpolate(sentimentLabel.value)}</span></div><div class="grid grid-cols-3 gap-4 text-center"><div><div class="text-lg font-semibold text-green-600">+12%</div><div class="text-xs text-gray-500">24h Change</div></div><div><div class="text-lg font-semibold text-blue-600">${ssrInterpolate(__props.projectCount)}</div><div class="text-xs text-gray-500">Projects</div></div><div><div class="text-lg font-semibold text-purple-600">${ssrInterpolate(__props.analysisCount)}</div><div class="text-xs text-gray-500">Analyses</div></div></div></div></div>`);
    };
  }
};
const _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Analytics/SentimentGauge.vue");
  return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
const _sfc_main$4 = {
  __name: "RiskMatrix",
  __ssrInlineRender: true,
  setup(__props) {
    const selectedRisk = ref(null);
    const lastUpdated = ref("2 min ago");
    const riskMatrix = ref([
      // Very Low Impact
      [
        { count: 15, examples: ["Code style warnings", "Documentation gaps"] },
        { count: 8, examples: ["Minor optimization opportunities"] },
        { count: 3, examples: ["Low-impact performance issues"] },
        { count: 1, examples: ["Rare edge case handling"] },
        { count: 0, examples: [] }
      ],
      // Low Impact
      [
        { count: 12, examples: ["Input validation suggestions"] },
        { count: 18, examples: ["Gas optimization hints"] },
        { count: 7, examples: ["Event emission improvements"] },
        { count: 2, examples: ["Minor access control issues"] },
        { count: 1, examples: ["Low-impact logic flaws"] }
      ],
      // Medium Impact
      [
        { count: 5, examples: ["Moderate gas inefficiencies"] },
        { count: 9, examples: ["State variable optimizations"] },
        { count: 12, examples: ["Function visibility issues"] },
        { count: 6, examples: ["Moderate security concerns"] },
        { count: 3, examples: ["Medium-risk vulnerabilities"] }
      ],
      // High Impact
      [
        { count: 2, examples: ["Potential front-running"] },
        { count: 4, examples: ["Access control weaknesses"] },
        { count: 8, examples: ["State manipulation risks"] },
        { count: 11, examples: ["High-value vulnerabilities"] },
        { count: 7, examples: ["Critical security flaws"] }
      ],
      // Very High Impact
      [
        { count: 0, examples: [] },
        { count: 1, examples: ["Potential fund locks"] },
        { count: 2, examples: ["Reentrancy vulnerabilities"] },
        { count: 5, examples: ["Critical exploit vectors"] },
        { count: 3, examples: ["Severe security breaches"] }
      ]
    ]);
    const getRiskCellClass = (impact, probability) => {
      const riskLevel = impact + probability;
      if (riskLevel >= 7) return "bg-red-100 border-red-300 text-red-800";
      if (riskLevel >= 5) return "bg-orange-100 border-orange-300 text-orange-800";
      if (riskLevel >= 3) return "bg-yellow-100 border-yellow-300 text-yellow-800";
      return "bg-green-100 border-green-300 text-green-800";
    };
    const getImpactLabel = (level) => {
      const labels = ["Very Low", "Low", "Medium", "High", "Very High"];
      return labels[level] || "Unknown";
    };
    const getProbabilityLabel = (level) => {
      const labels = ["Very Low", "Low", "Medium", "High", "Very High"];
      return labels[level] || "Unknown";
    };
    const criticalCount = computed(() => {
      return riskMatrix.value.reduce((total, row, impact) => {
        return total + row.reduce((rowTotal, cell, prob) => {
          return impact + prob >= 7 ? rowTotal + cell.count : rowTotal;
        }, 0);
      }, 0);
    });
    const highCount = computed(() => {
      return riskMatrix.value.reduce((total, row, impact) => {
        return total + row.reduce((rowTotal, cell, prob) => {
          const riskLevel = impact + prob;
          return riskLevel >= 5 && riskLevel < 7 ? rowTotal + cell.count : rowTotal;
        }, 0);
      }, 0);
    });
    const mediumCount = computed(() => {
      return riskMatrix.value.reduce((total, row, impact) => {
        return total + row.reduce((rowTotal, cell, prob) => {
          const riskLevel = impact + prob;
          return riskLevel >= 3 && riskLevel < 5 ? rowTotal + cell.count : rowTotal;
        }, 0);
      }, 0);
    });
    const lowCount = computed(() => {
      return riskMatrix.value.reduce((total, row, impact) => {
        return total + row.reduce((rowTotal, cell, prob) => {
          const riskLevel = impact + prob;
          return riskLevel < 3 ? rowTotal + cell.count : rowTotal;
        }, 0);
      }, 0);
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, _attrs))}><div class="flex items-center justify-between mb-4"><h3 class="text-lg font-semibold text-gray-900">Risk Assessment Matrix</h3><div class="flex items-center space-x-2"><span class="text-sm text-gray-500">Last updated:</span><span class="text-sm font-medium text-gray-900">${ssrInterpolate(lastUpdated.value)}</span></div></div><div class="grid grid-cols-6 gap-1 mb-4"><div class="text-center text-xs font-medium text-gray-600 p-2">Impact</div><div class="text-center text-xs font-medium text-gray-600 p-2">Very Low</div><div class="text-center text-xs font-medium text-gray-600 p-2">Low</div><div class="text-center text-xs font-medium text-gray-600 p-2">Medium</div><div class="text-center text-xs font-medium text-gray-600 p-2">High</div><div class="text-center text-xs font-medium text-gray-600 p-2">Very High</div><div class="text-center text-xs font-medium text-gray-600 p-2">Very High</div><!--[-->`);
      ssrRenderList(riskMatrix.value[4], (cell, index) => {
        _push(`<div class="${ssrRenderClass([
          "p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105",
          getRiskCellClass(4, index),
          cell.count > 0 ? "shadow-sm" : ""
        ])}">${ssrInterpolate(cell.count)}</div>`);
      });
      _push(`<!--]--><div class="text-center text-xs font-medium text-gray-600 p-2">High</div><!--[-->`);
      ssrRenderList(riskMatrix.value[3], (cell, index) => {
        _push(`<div class="${ssrRenderClass([
          "p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105",
          getRiskCellClass(3, index),
          cell.count > 0 ? "shadow-sm" : ""
        ])}">${ssrInterpolate(cell.count)}</div>`);
      });
      _push(`<!--]--><div class="text-center text-xs font-medium text-gray-600 p-2">Medium</div><!--[-->`);
      ssrRenderList(riskMatrix.value[2], (cell, index) => {
        _push(`<div class="${ssrRenderClass([
          "p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105",
          getRiskCellClass(2, index),
          cell.count > 0 ? "shadow-sm" : ""
        ])}">${ssrInterpolate(cell.count)}</div>`);
      });
      _push(`<!--]--><div class="text-center text-xs font-medium text-gray-600 p-2">Low</div><!--[-->`);
      ssrRenderList(riskMatrix.value[1], (cell, index) => {
        _push(`<div class="${ssrRenderClass([
          "p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105",
          getRiskCellClass(1, index),
          cell.count > 0 ? "shadow-sm" : ""
        ])}">${ssrInterpolate(cell.count)}</div>`);
      });
      _push(`<!--]--><div class="text-center text-xs font-medium text-gray-600 p-2">Very Low</div><!--[-->`);
      ssrRenderList(riskMatrix.value[0], (cell, index) => {
        _push(`<div class="${ssrRenderClass([
          "p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105",
          getRiskCellClass(0, index),
          cell.count > 0 ? "shadow-sm" : ""
        ])}">${ssrInterpolate(cell.count)}</div>`);
      });
      _push(`<!--]--></div><div class="grid grid-cols-6 gap-1 mb-4"><div></div><div class="text-center text-xs text-gray-600 font-medium">Probability</div></div><div class="grid grid-cols-4 gap-4 pt-4 border-t border-gray-200"><div class="text-center"><div class="text-lg font-semibold text-red-600">${ssrInterpolate(criticalCount.value)}</div><div class="text-xs text-gray-500">Critical</div></div><div class="text-center"><div class="text-lg font-semibold text-orange-600">${ssrInterpolate(highCount.value)}</div><div class="text-xs text-gray-500">High</div></div><div class="text-center"><div class="text-lg font-semibold text-yellow-600">${ssrInterpolate(mediumCount.value)}</div><div class="text-xs text-gray-500">Medium</div></div><div class="text-center"><div class="text-lg font-semibold text-green-600">${ssrInterpolate(lowCount.value)}</div><div class="text-xs text-gray-500">Low</div></div></div>`);
      if (selectedRisk.value) {
        _push(`<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"><div class="bg-white rounded-lg p-6 max-w-md mx-4"><h4 class="text-lg font-semibold mb-2">Risk Details</h4><p class="text-sm text-gray-600 mb-4"><strong>Impact:</strong> ${ssrInterpolate(getImpactLabel(selectedRisk.value.impact))}<br><strong>Probability:</strong> ${ssrInterpolate(getProbabilityLabel(selectedRisk.value.probability))}<br><strong>Findings:</strong> ${ssrInterpolate(selectedRisk.value.count)}</p><div class="space-y-2"><!--[-->`);
        ssrRenderList(selectedRisk.value.examples, (finding) => {
          _push(`<div class="text-sm text-gray-700 bg-gray-50 p-2 rounded">${ssrInterpolate(finding)}</div>`);
        });
        _push(`<!--]--></div><button class="mt-4 w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 transition-colors"> Close </button></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div>`);
    };
  }
};
const _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Analytics/RiskMatrix.vue");
  return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
const _sfc_main$3 = {
  __name: "NetworkStatus",
  __ssrInlineRender: true,
  setup(__props) {
    const refreshing = ref(false);
    const networks = ref([
      {
        id: "ethereum",
        name: "Ethereum",
        explorer: "Etherscan.io",
        logo: "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjNjI3RUVBIi8+PHBhdGggZD0iTTguNzEyIDkuMTA2IDEyLjgzNyA5LjQ5M2EuNS41IDAgMCAxIC4zOTguNzI0bC0yLjA2NSA0LjEzIDIuMDY1IDQuMTNhLjUuNSAwIDAgMS0uMzk4LjcyNGwtNC4xMjUuMzg3YS41LjUgMCAwIDEtLjU0LS40OTdsLS4zODctNC4xMjVhLjUuNSAwIDAgMSAwLS4wOTlsLjM4Ny00LjEyNWEuNS41IDAgMCAxIC41NC0uNDk3eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=",
        status: "active",
        responseTime: 120,
        requestsToday: 1247,
        successRate: 99.2
      },
      {
        id: "polygon",
        name: "Polygon",
        explorer: "PolygonScan.com",
        logo: "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjODI0N0U1Ii8+PHBhdGggZD0iTTEyLjggOC4yNGE0IDQgMCAwIDEgNi40IDBsNi40IDguOGE0IDQgMCAwIDEtMy4yIDYuNGgtMTIuOGE0IDQgMCAwIDEtMy4yLTYuNGw2LjQtOC44eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=",
        status: "active",
        responseTime: 95,
        requestsToday: 856,
        successRate: 98.7
      },
      {
        id: "bsc",
        name: "BSC",
        explorer: "BscScan.com",
        logo: "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjRjNCQjAwIi8+PHBhdGggZD0iTTE2IDZsNi4xODQgNi4xODQtMi4yNTcgMi4yNTdMMTYgMTAuNTE0bC0zLjkyNyAzLjkyNy0yLjI1Ny0yLjI1N0wxNiA2em02LjE4NCAxMC4xODRMMjQgMTQuNDM3di0yLjI1N2wtMi4yNTcgMi4yNTctMi4yNTctMi4yNTdWMTZsMS44MTYtMS44MTZ6bS0xMi4zNjggMGwyLjI1Ny0yLjI1N1YxNkwxMC4yNTcgMTcuODE2IDggMTZWMTZsMS44MTYtMS44MTZMMTIgMTYuMTg0eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=",
        status: "active",
        responseTime: 180,
        requestsToday: 634,
        successRate: 97.8
      },
      {
        id: "arbitrum",
        name: "Arbitrum",
        explorer: "Arbiscan.io",
        logo: "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjMkQ3NEJCIi8+PHBhdGggZD0iTTE2IDZhMTAgMTAgMCAxIDEgMCAyMEExMCAxMCAwIDAgMSAxNiA2eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=",
        status: "slow",
        responseTime: 340,
        requestsToday: 423,
        successRate: 96.1
      },
      {
        id: "optimism",
        name: "Optimism",
        explorer: "Optimistic.etherscan.io",
        logo: "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjRkYwNDIwIi8+PHBhdGggZD0iTTE2IDZhMTAgMTAgMCAxIDEgMCAyMEExMCAxMCAwIDAgMSAxNiA2eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=",
        status: "maintenance",
        responseTime: 0,
        requestsToday: 0,
        successRate: 0
      }
    ]);
    const getStatusColor = (status) => {
      switch (status) {
        case "active":
          return "bg-green-400";
        case "slow":
          return "bg-yellow-400";
        case "maintenance":
          return "bg-red-400";
        case "offline":
          return "bg-gray-400";
        default:
          return "bg-gray-400";
      }
    };
    const getStatusTextColor = (status) => {
      switch (status) {
        case "active":
          return "text-green-600";
        case "slow":
          return "text-yellow-600";
        case "maintenance":
          return "text-red-600";
        case "offline":
          return "text-gray-600";
        default:
          return "text-gray-600";
      }
    };
    const getStatusText = (status) => {
      switch (status) {
        case "active":
          return "Active";
        case "slow":
          return "Slow";
        case "maintenance":
          return "Maintenance";
        case "offline":
          return "Offline";
        default:
          return "Unknown";
      }
    };
    const totalRequests = computed(() => {
      return networks.value.reduce((total, network) => total + network.requestsToday, 0);
    });
    const successRate = computed(() => {
      const activeNetworks = networks.value.filter((n) => n.status === "active" || n.status === "slow");
      if (activeNetworks.length === 0) return 0;
      const avgRate = activeNetworks.reduce((sum, network) => sum + network.successRate, 0) / activeNetworks.length;
      return Math.round(avgRate * 10) / 10;
    });
    const avgResponseTime = computed(() => {
      const activeNetworks = networks.value.filter((n) => n.status === "active" || n.status === "slow");
      if (activeNetworks.length === 0) return 0;
      const avgTime = activeNetworks.reduce((sum, network) => sum + network.responseTime, 0) / activeNetworks.length;
      return Math.round(avgTime);
    });
    const rateLimitStatus = computed(() => {
      const dailyLimit = 5e3;
      const usagePercentage = totalRequests.value / dailyLimit * 100;
      return Math.min(Math.round(usagePercentage), 100);
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, _attrs))}><div class="flex items-center justify-between mb-4"><h3 class="text-lg font-semibold text-gray-900">Network Status</h3><button${ssrIncludeBooleanAttr(refreshing.value) ? " disabled" : ""} class="text-sm text-indigo-600 hover:text-indigo-700 font-medium disabled:opacity-50">${ssrInterpolate(refreshing.value ? "Refreshing..." : "Refresh")}</button></div><div class="space-y-4"><!--[-->`);
      ssrRenderList(networks.value, (network) => {
        _push(`<div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"><div class="flex items-center space-x-3"><div class="relative"><img${ssrRenderAttr("src", network.logo)}${ssrRenderAttr("alt", network.name)} class="w-8 h-8 rounded-full"><div class="${ssrRenderClass([
          "absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-white",
          getStatusColor(network.status)
        ])}"></div></div><div><h4 class="font-medium text-gray-900">${ssrInterpolate(network.name)}</h4><p class="text-sm text-gray-500">${ssrInterpolate(network.explorer)}</p></div></div><div class="text-right"><div class="flex items-center space-x-4"><div class="text-center"><div class="text-sm font-medium text-gray-900">${ssrInterpolate(network.responseTime)}ms</div><div class="text-xs text-gray-500">Response</div></div><div class="text-center"><div class="text-sm font-medium text-gray-900">${ssrInterpolate(network.requestsToday.toLocaleString())}</div><div class="text-xs text-gray-500">Requests</div></div><div class="text-center"><div class="${ssrRenderClass(["text-sm font-medium", getStatusTextColor(network.status)])}">${ssrInterpolate(getStatusText(network.status))}</div><div class="text-xs text-gray-500">Status</div></div></div></div></div>`);
      });
      _push(`<!--]--></div><div class="mt-6 pt-6 border-t border-gray-200"><h4 class="text-sm font-semibold text-gray-900 mb-3">API Usage Summary</h4><div class="grid grid-cols-2 md:grid-cols-4 gap-4"><div class="text-center"><div class="text-lg font-semibold text-blue-600">${ssrInterpolate(totalRequests.value.toLocaleString())}</div><div class="text-xs text-gray-500">Total Requests</div></div><div class="text-center"><div class="text-lg font-semibold text-green-600">${ssrInterpolate(successRate.value)}%</div><div class="text-xs text-gray-500">Success Rate</div></div><div class="text-center"><div class="text-lg font-semibold text-yellow-600">${ssrInterpolate(avgResponseTime.value)}ms</div><div class="text-xs text-gray-500">Avg Response</div></div><div class="text-center"><div class="text-lg font-semibold text-purple-600">${ssrInterpolate(rateLimitStatus.value)}%</div><div class="text-xs text-gray-500">Rate Limit</div></div></div></div><div class="mt-4"><div class="flex items-center justify-between text-sm mb-2"><span class="text-gray-600">Rate Limit Usage</span><span class="text-gray-900 font-medium">${ssrInterpolate(rateLimitStatus.value)}% of daily limit</span></div><div class="w-full bg-gray-200 rounded-full h-2"><div class="${ssrRenderClass([
        "h-2 rounded-full transition-all duration-500",
        rateLimitStatus.value < 70 ? "bg-green-500" : rateLimitStatus.value < 90 ? "bg-yellow-500" : "bg-red-500"
      ])}" style="${ssrRenderStyle({ width: rateLimitStatus.value + "%" })}"></div></div></div></div>`);
    };
  }
};
const _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Analytics/NetworkStatus.vue");
  return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
const _sfc_main$2 = {
  __name: "RealTimeMonitor",
  __ssrInlineRender: true,
  setup(__props) {
    const isMonitoring = ref(true);
    const monitoringInterval = ref(null);
    const activeAnalyses = ref([
      {
        id: 1,
        contractName: "UniswapV4Router",
        network: "Ethereum",
        type: "Security Audit",
        status: "analyzing",
        progress: 67,
        duration: 145,
        // seconds
        findingsCount: 8,
        gasAnalyzed: 245e4,
        currentStep: "Reentrancy Analysis",
        eta: "2 min remaining",
        recentFindings: [
          { id: 1, title: "Potential front-running in swap function", severity: "medium", timestamp: "30s ago" },
          { id: 2, title: "Gas optimization opportunity found", severity: "low", timestamp: "1m ago" }
        ]
      },
      {
        id: 2,
        contractName: "AAVE LendingPool",
        network: "Polygon",
        type: "Full Analysis",
        status: "analyzing",
        progress: 23,
        duration: 67,
        findingsCount: 3,
        gasAnalyzed: 89e4,
        currentStep: "Function Mapping",
        eta: "8 min remaining",
        recentFindings: [
          { id: 3, title: "Access control validation required", severity: "high", timestamp: "15s ago" }
        ]
      },
      {
        id: 3,
        contractName: "CompoundGovernor",
        network: "Ethereum",
        type: "Quick Scan",
        status: "finalizing",
        progress: 94,
        duration: 89,
        findingsCount: 12,
        gasAnalyzed: 175e4,
        currentStep: "Report Generation",
        eta: "30s remaining",
        recentFindings: [
          { id: 4, title: "Integer overflow protection needed", severity: "critical", timestamp: "5s ago" },
          { id: 5, title: "Event emission optimization", severity: "low", timestamp: "45s ago" }
        ]
      }
    ]);
    const queuedAnalyses = ref([
      {
        id: 4,
        contractName: "SushiSwapV3Factory",
        network: "Ethereum",
        type: "Security Audit",
        estimatedStart: "3 min"
      },
      {
        id: 5,
        contractName: "PancakeSwapRouter",
        network: "BSC",
        type: "Full Analysis",
        estimatedStart: "7 min"
      },
      {
        id: 6,
        contractName: "YearnVaultV2",
        network: "Ethereum",
        type: "Quick Scan",
        estimatedStart: "12 min"
      },
      {
        id: 7,
        contractName: "CurveStableSwap",
        network: "Polygon",
        type: "Security Audit",
        estimatedStart: "18 min"
      }
    ]);
    const totalAnalysesToday = ref(47);
    const averageCompletionTime = ref(156);
    const totalFindingsToday = ref(203);
    const systemLoad = ref(73);
    const getStatusColor = (status) => {
      switch (status) {
        case "analyzing":
          return "bg-blue-500";
        case "finalizing":
          return "bg-green-500";
        case "queued":
          return "bg-yellow-500";
        default:
          return "bg-gray-500";
      }
    };
    const getSeverityColor = (severity) => {
      switch (severity) {
        case "critical":
          return "bg-red-500";
        case "high":
          return "bg-orange-500";
        case "medium":
          return "bg-yellow-500";
        case "low":
          return "bg-blue-500";
        default:
          return "bg-gray-500";
      }
    };
    const formatDuration = (seconds) => {
      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = seconds % 60;
      return minutes > 0 ? `${minutes}m ${remainingSeconds}s` : `${remainingSeconds}s`;
    };
    const simulateProgress = () => {
      activeAnalyses.value.forEach((analysis) => {
        if (analysis.progress < 100) {
          const increment = Math.random() * 3 + 0.5;
          analysis.progress = Math.min(100, analysis.progress + increment);
          analysis.duration += 2;
          if (Math.random() > 0.9) {
            analysis.findingsCount += 1;
            const severities = ["low", "medium", "high", "critical"];
            const titles = [
              "Gas optimization opportunity",
              "Access control check needed",
              "State variable visibility issue",
              "Potential reentrancy vulnerability",
              "Integer overflow protection required",
              "Event emission missing",
              "Function modifier validation"
            ];
            analysis.recentFindings.unshift({
              id: Date.now(),
              title: titles[Math.floor(Math.random() * titles.length)],
              severity: severities[Math.floor(Math.random() * severities.length)],
              timestamp: "just now"
            });
            analysis.recentFindings = analysis.recentFindings.slice(0, 5);
          }
          if (analysis.progress > 90) {
            analysis.currentStep = "Report Generation";
            analysis.eta = "30s remaining";
          } else if (analysis.progress > 70) {
            analysis.currentStep = "Vulnerability Assessment";
            analysis.eta = "2 min remaining";
          } else if (analysis.progress > 40) {
            analysis.currentStep = "Code Pattern Analysis";
            analysis.eta = "5 min remaining";
          } else if (analysis.progress > 20) {
            analysis.currentStep = "Function Mapping";
            analysis.eta = "8 min remaining";
          } else {
            analysis.currentStep = "Contract Parsing";
            analysis.eta = "Calculating...";
          }
        }
      });
      if (Math.random() > 0.8) {
        totalFindingsToday.value += Math.floor(Math.random() * 3);
        systemLoad.value = Math.max(45, Math.min(95, systemLoad.value + (Math.random() - 0.5) * 10));
      }
    };
    const startMonitoring = () => {
      if (monitoringInterval.value) return;
      monitoringInterval.value = setInterval(simulateProgress, 2e3);
    };
    const stopMonitoring = () => {
      if (monitoringInterval.value) {
        clearInterval(monitoringInterval.value);
        monitoringInterval.value = null;
      }
    };
    onMounted(() => {
      if (isMonitoring.value) {
        startMonitoring();
      }
    });
    onUnmounted(() => {
      stopMonitoring();
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, _attrs))}><div class="flex items-center justify-between mb-6"><div><h3 class="text-lg font-semibold text-gray-900">Real-Time Analysis Monitor</h3><p class="text-sm text-gray-600">Live monitoring of ongoing blockchain analyses</p></div><div class="flex items-center space-x-3"><div class="flex items-center"><div class="w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2"></div><span class="text-sm text-gray-600">${ssrInterpolate(activeAnalyses.value.length)} active</span></div><button class="${ssrRenderClass([
        "px-3 py-1 text-xs font-medium rounded-md transition-colors",
        isMonitoring.value ? "bg-red-100 text-red-700 hover:bg-red-200" : "bg-green-100 text-green-700 hover:bg-green-200"
      ])}">${ssrInterpolate(isMonitoring.value ? "Stop" : "Start")} Monitoring </button></div></div><div class="space-y-3 mb-6"><!--[-->`);
      ssrRenderList(activeAnalyses.value, (analysis) => {
        _push(`<div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow"><div class="flex items-center justify-between"><div class="flex-1"><div class="flex items-center space-x-3"><div class="flex items-center"><div class="${ssrRenderClass(["w-3 h-3 rounded-full animate-pulse mr-2", getStatusColor(analysis.status)])}"></div><h4 class="text-sm font-medium text-gray-900">${ssrInterpolate(analysis.contractName)}</h4></div><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${ssrInterpolate(analysis.network)}</span><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">${ssrInterpolate(analysis.type)}</span></div><div class="mt-2 flex items-center space-x-4 text-sm text-gray-500"><span>${ssrInterpolate(analysis.progress)}% complete</span><span>•</span><span>${ssrInterpolate(analysis.findingsCount)} findings</span><span>•</span><span>${ssrInterpolate(formatDuration(analysis.duration))}</span><span>•</span><span>${ssrInterpolate(analysis.gasAnalyzed.toLocaleString())} gas units</span></div></div><div class="flex items-center space-x-2"><div class="text-right"><div class="text-sm font-medium text-gray-900">${ssrInterpolate(analysis.currentStep)}</div><div class="text-xs text-gray-500">${ssrInterpolate(analysis.eta || "Calculating...")}</div></div></div></div><div class="mt-3"><div class="flex items-center justify-between text-xs text-gray-600 mb-1"><span>Analysis Progress</span><span>${ssrInterpolate(analysis.progress)}%</span></div><div class="w-full bg-gray-200 rounded-full h-2"><div class="${ssrRenderClass([
          "h-2 rounded-full transition-all duration-500",
          analysis.progress < 30 ? "bg-blue-500" : analysis.progress < 70 ? "bg-yellow-500" : "bg-green-500"
        ])}" style="${ssrRenderStyle({ width: analysis.progress + "%" })}"></div></div></div>`);
        if (analysis.recentFindings.length > 0) {
          _push(`<div class="mt-3 pt-3 border-t border-gray-100"><div class="text-xs text-gray-600 mb-2">Recent Findings:</div><div class="space-y-1"><!--[-->`);
          ssrRenderList(analysis.recentFindings.slice(0, 2), (finding) => {
            _push(`<div class="flex items-center space-x-2 text-xs"><span class="${ssrRenderClass(["inline-flex w-2 h-2 rounded-full", getSeverityColor(finding.severity)])}"></span><span class="text-gray-700 truncate">${ssrInterpolate(finding.title)}</span><span class="text-gray-500">${ssrInterpolate(finding.timestamp)}</span></div>`);
          });
          _push(`<!--]--></div></div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div>`);
      });
      _push(`<!--]--></div>`);
      if (queuedAnalyses.value.length > 0) {
        _push(`<div class="border-t border-gray-200 pt-4"><h4 class="text-sm font-semibold text-gray-900 mb-3">Analysis Queue (${ssrInterpolate(queuedAnalyses.value.length)})</h4><div class="space-y-2"><!--[-->`);
        ssrRenderList(queuedAnalyses.value.slice(0, 3), (queued, index) => {
          _push(`<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"><div class="flex items-center space-x-3"><div class="text-sm font-medium text-gray-600">#${ssrInterpolate(index + 1)}</div><div><div class="text-sm font-medium text-gray-900">${ssrInterpolate(queued.contractName)}</div><div class="text-xs text-gray-500">${ssrInterpolate(queued.network)} • ${ssrInterpolate(queued.type)}</div></div></div><div class="text-xs text-gray-500"> ETA: ${ssrInterpolate(queued.estimatedStart)}</div></div>`);
        });
        _push(`<!--]-->`);
        if (queuedAnalyses.value.length > 3) {
          _push(`<div class="text-center"><span class="text-xs text-gray-500">+${ssrInterpolate(queuedAnalyses.value.length - 3)} more in queue</span></div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<div class="mt-6 pt-4 border-t border-gray-200"><div class="grid grid-cols-2 md:grid-cols-4 gap-4"><div class="text-center"><div class="text-lg font-semibold text-blue-600">${ssrInterpolate(totalAnalysesToday.value)}</div><div class="text-xs text-gray-500">Analyses Today</div></div><div class="text-center"><div class="text-lg font-semibold text-green-600">${ssrInterpolate(averageCompletionTime.value)}s</div><div class="text-xs text-gray-500">Avg Time</div></div><div class="text-center"><div class="text-lg font-semibold text-purple-600">${ssrInterpolate(totalFindingsToday.value)}</div><div class="text-xs text-gray-500">Findings Today</div></div><div class="text-center"><div class="text-lg font-semibold text-orange-600">${ssrInterpolate(systemLoad.value)}%</div><div class="text-xs text-gray-500">System Load</div></div></div></div></div>`);
    };
  }
};
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Demo/RealTimeMonitor.vue");
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const _sfc_main$1 = {
  __name: "BlockchainExplorer",
  __ssrInlineRender: true,
  setup(__props) {
    const selectedNetwork = ref("ethereum");
    const searchQuery = ref("");
    const searching = ref(false);
    const analyzing = ref(false);
    const searchResults = ref(null);
    const analysisResults = ref(null);
    const networks = [
      { id: "ethereum", name: "Ethereum Mainnet" },
      { id: "polygon", name: "Polygon" },
      { id: "bsc", name: "BSC" },
      { id: "arbitrum", name: "Arbitrum" },
      { id: "optimism", name: "Optimism" }
    ];
    const quickExamples = [
      { name: "Uniswap V3", address: "0x1F98431c8aD98523631AE4a59f267346ea31F984", verified: true },
      { name: "USDC Token", address: "0xA0b86a33E6417c8f38B9D42FC71A1D7e70e09E4a", verified: true },
      { name: "Compound", address: "0x3d9819210A31b4961b30EF54bE2aeD79B9c9Cd3B", verified: true },
      { name: "AAVE V3", address: "0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2", verified: true }
    ];
    const getNetworkName = (networkId) => {
      var _a;
      return ((_a = networks.find((n) => n.id === networkId)) == null ? void 0 : _a.name) || networkId;
    };
    const getVerificationBadge = (verified) => {
      return verified ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800";
    };
    const getSeverityColor = (severity) => {
      switch (severity) {
        case "critical":
          return "bg-red-500";
        case "high":
          return "bg-orange-500";
        case "medium":
          return "bg-yellow-500";
        case "low":
          return "bg-blue-500";
        default:
          return "bg-gray-500";
      }
    };
    return (_ctx, _push, _parent, _attrs) => {
      var _a;
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, _attrs))}><div class="flex items-center justify-between mb-6"><div><h3 class="text-lg font-semibold text-gray-900">Interactive Blockchain Explorer</h3><p class="text-sm text-gray-600">Search and analyze contracts across multiple networks</p></div><div class="flex items-center space-x-2"><select class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"><!--[-->`);
      ssrRenderList(networks, (network) => {
        _push(`<option${ssrRenderAttr("value", network.id)}${ssrIncludeBooleanAttr(Array.isArray(selectedNetwork.value) ? ssrLooseContain(selectedNetwork.value, network.id) : ssrLooseEqual(selectedNetwork.value, network.id)) ? " selected" : ""}>${ssrInterpolate(network.name)}</option>`);
      });
      _push(`<!--]--></select></div></div><div class="mb-6"><div class="flex space-x-3"><div class="flex-1"><input${ssrRenderAttr("value", searchQuery.value)} type="text" placeholder="Enter contract address, transaction hash, or ENS name..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></div><button${ssrIncludeBooleanAttr(searching.value || !searchQuery.value.trim()) ? " disabled" : ""} class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">${ssrInterpolate(searching.value ? "Searching..." : "Analyze")}</button></div><div class="mt-3"><div class="text-xs text-gray-500 mb-2">Quick examples:</div><div class="flex flex-wrap gap-2"><!--[-->`);
      ssrRenderList(quickExamples, (example) => {
        _push(`<button class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded transition-colors">${ssrInterpolate(example.name)}</button>`);
      });
      _push(`<!--]--></div></div></div>`);
      if (searchResults.value) {
        _push(`<div class="space-y-6"><div class="border border-gray-200 rounded-lg p-4"><div class="flex items-center justify-between mb-4"><h4 class="text-lg font-semibold text-gray-900">${ssrInterpolate(searchResults.value.name || "Contract")}</h4><div class="flex items-center space-x-2"><span class="${ssrRenderClass(["inline-flex items-center px-2 py-1 rounded-full text-xs font-medium", getVerificationBadge(searchResults.value.verified)])}">${ssrInterpolate(searchResults.value.verified ? "Verified" : "Unverified")}</span><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${ssrInterpolate(getNetworkName(selectedNetwork.value))}</span></div></div><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4"><div><div class="text-xs text-gray-500">Address</div><div class="text-sm font-mono text-gray-900 break-all">${ssrInterpolate(searchResults.value.address)}</div></div><div><div class="text-xs text-gray-500">Balance</div><div class="text-sm font-medium text-gray-900">${ssrInterpolate(searchResults.value.balance)} ETH</div></div><div><div class="text-xs text-gray-500">Transactions</div><div class="text-sm font-medium text-gray-900">${ssrInterpolate(((_a = searchResults.value.transactionCount) == null ? void 0 : _a.toLocaleString()) || "N/A")}</div></div><div><div class="text-xs text-gray-500">Creation Date</div><div class="text-sm font-medium text-gray-900">${ssrInterpolate(searchResults.value.creationDate || "Unknown")}</div></div></div><div class="flex flex-wrap gap-2"><button${ssrIncludeBooleanAttr(analyzing.value) ? " disabled" : ""} class="flex items-center space-x-2 px-3 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 disabled:opacity-50 transition-colors text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span>${ssrInterpolate(analyzing.value ? "Analyzing..." : "Security Audit")}</span></button><button class="flex items-center space-x-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg><span>Sentiment Analysis</span></button><button${ssrIncludeBooleanAttr(!searchResults.value.verified) ? " disabled" : ""} class="flex items-center space-x-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 disabled:opacity-50 transition-colors text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg><span>View Source</span></button></div></div>`);
        if (analysisResults.value) {
          _push(`<div class="border border-gray-200 rounded-lg p-4"><h4 class="text-lg font-semibold text-gray-900 mb-4">Analysis Results</h4><div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4"><div class="text-center p-4 bg-red-50 rounded-lg"><div class="text-2xl font-bold text-red-600">${ssrInterpolate(analysisResults.value.criticalFindings)}</div><div class="text-sm text-red-700">Critical Issues</div></div><div class="text-center p-4 bg-yellow-50 rounded-lg"><div class="text-2xl font-bold text-yellow-600">${ssrInterpolate(analysisResults.value.warningFindings)}</div><div class="text-sm text-yellow-700">Warnings</div></div><div class="text-center p-4 bg-green-50 rounded-lg"><div class="text-2xl font-bold text-green-600">${ssrInterpolate(analysisResults.value.securityScore)}%</div><div class="text-sm text-green-700">Security Score</div></div></div><div class="space-y-3"><h5 class="font-medium text-gray-900">Key Findings:</h5><!--[-->`);
          ssrRenderList(analysisResults.value.keyFindings, (finding) => {
            _push(`<div class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg"><div class="${ssrRenderClass(["w-3 h-3 rounded-full mt-1.5 flex-shrink-0", getSeverityColor(finding.severity)])}"></div><div class="flex-1"><div class="text-sm font-medium text-gray-900">${ssrInterpolate(finding.title)}</div><div class="text-xs text-gray-600 mt-1">${ssrInterpolate(finding.description)}</div><div class="text-xs text-gray-500 mt-1"> Function: <code class="bg-gray-100 px-1 rounded">${ssrInterpolate(finding.function)}</code> • Line: ${ssrInterpolate(finding.line)}</div></div></div>`);
          });
          _push(`<!--]--></div></div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div>`);
      } else if (searching.value) {
        _push(`<div class="text-center py-12"><div class="inline-flex items-center space-x-3"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div><span class="text-gray-600">Fetching contract data from ${ssrInterpolate(getNetworkName(selectedNetwork.value))}...</span></div></div>`);
      } else {
        _push(`<div class="text-center py-12"><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900">Search blockchain contracts</h3><p class="mt-1 text-sm text-gray-500">Enter a contract address to begin analysis</p></div>`);
      }
      _push(`</div>`);
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Demo/BlockchainExplorer.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = {
  __name: "Dashboard",
  __ssrInlineRender: true,
  setup(__props) {
    const stats = ref({
      totalProjects: 47,
      activeAnalyses: 12,
      criticalFindings: 3,
      avgSentiment: 0.72,
      lastAnalysis: "2 minutes ago",
      securityScore: 85,
      riskLevel: "Medium",
      trendsImproving: 8
    });
    const recentProjects = ref([
      {
        id: 1,
        name: "UniswapV4 Core",
        network: "Ethereum",
        status: "analyzing",
        riskLevel: "low",
        lastAnalyzed: "5 min ago",
        findings: 2,
        sentiment: 0.85
      },
      {
        id: 2,
        name: "AAVE V3 Lending",
        network: "Polygon",
        status: "completed",
        riskLevel: "medium",
        lastAnalyzed: "1 hour ago",
        findings: 7,
        sentiment: 0.68
      },
      {
        id: 3,
        name: "Compound Governor",
        network: "Ethereum",
        status: "completed",
        riskLevel: "high",
        lastAnalyzed: "3 hours ago",
        findings: 15,
        sentiment: 0.45
      }
    ]);
    const criticalFindings = ref([
      {
        id: 1,
        title: "Reentrancy Vulnerability",
        severity: "critical",
        contract: "LendingPool.sol",
        function: "withdraw()",
        impact: "High",
        cvss: 9.1
      },
      {
        id: 2,
        title: "Integer Overflow Risk",
        severity: "high",
        contract: "TokenVault.sol",
        function: "calculateRewards()",
        impact: "Medium",
        cvss: 7.5
      },
      {
        id: 3,
        title: "Access Control Bypass",
        severity: "medium",
        contract: "Governance.sol",
        function: "executeProposal()",
        impact: "Low",
        cvss: 5.3
      }
    ]);
    const aiInsights = ref([
      {
        type: "security",
        title: "Pattern Recognition Alert",
        message: "Detected similar vulnerability patterns across 3 contracts. Consider implementing unified security library.",
        confidence: 94,
        action: "Review Pattern"
      },
      {
        type: "performance",
        title: "Gas Optimization Opportunity",
        message: "Function batching could reduce gas costs by 35% in high-frequency operations.",
        confidence: 87,
        action: "Optimize Gas"
      },
      {
        type: "sentiment",
        title: "Community Sentiment Shift",
        message: "Positive sentiment increased 23% after latest security audit completion.",
        confidence: 91,
        action: "View Trends"
      }
    ]);
    const getSeverityColor = (severity) => {
      switch (severity) {
        case "critical":
          return "text-red-600 bg-red-50";
        case "high":
          return "text-orange-600 bg-orange-50";
        case "medium":
          return "text-yellow-600 bg-yellow-50";
        case "low":
          return "text-green-600 bg-green-50";
        default:
          return "text-gray-600 bg-gray-50";
      }
    };
    const getRiskColor = (risk) => {
      switch (risk) {
        case "high":
          return "text-red-600 bg-red-100";
        case "medium":
          return "text-yellow-600 bg-yellow-100";
        case "low":
          return "text-green-600 bg-green-100";
        default:
          return "text-gray-600 bg-gray-100";
      }
    };
    const getSentimentIcon = (sentiment) => {
      if (sentiment > 0.1) return ArrowTrendingUpIcon;
      if (sentiment < -0.1) return ArrowTrendingDownIcon;
      return MinusIcon;
    };
    const getSentimentColor = (sentiment) => {
      if (sentiment > 0.1) return "text-green-500";
      if (sentiment < -0.1) return "text-red-500";
      return "text-gray-500";
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "AI Blockchain Analytics Dashboard" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$7, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><h2 class="text-2xl font-bold leading-tight text-gray-900"${_scopeId}> AI Blockchain Analytics </h2><p class="text-sm text-gray-600 mt-1"${_scopeId}> Real-time security analysis and sentiment monitoring </p></div><div class="flex items-center space-x-4"${_scopeId}><div class="flex items-center text-sm text-gray-500"${_scopeId}><div class="w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2"${_scopeId}></div> Live Monitoring Active </div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "flex items-center justify-between" }, [
                createVNode("div", null, [
                  createVNode("h2", { class: "text-2xl font-bold leading-tight text-gray-900" }, " AI Blockchain Analytics "),
                  createVNode("p", { class: "text-sm text-gray-600 mt-1" }, " Real-time security analysis and sentiment monitoring ")
                ]),
                createVNode("div", { class: "flex items-center space-x-4" }, [
                  createVNode("div", { class: "flex items-center text-sm text-gray-500" }, [
                    createVNode("div", { class: "w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2" }),
                    createTextVNode(" Live Monitoring Active ")
                  ])
                ])
              ])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="py-6"${_scopeId}><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"${_scopeId}><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"${_scopeId}><div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="p-6"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(ChartBarIcon), { class: "h-8 w-8 text-indigo-600" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-4"${_scopeId}><p class="text-sm font-medium text-gray-500"${_scopeId}>Total Projects</p><p class="text-2xl font-semibold text-gray-900"${_scopeId}>${ssrInterpolate(stats.value.totalProjects)}</p></div></div></div></div><div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="p-6"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(CpuChipIcon), { class: "h-8 w-8 text-blue-600" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-4"${_scopeId}><p class="text-sm font-medium text-gray-500"${_scopeId}>Active Analyses</p><div class="flex items-center"${_scopeId}><p class="text-2xl font-semibold text-gray-900"${_scopeId}>${ssrInterpolate(stats.value.activeAnalyses)}</p><div class="ml-2 flex items-center text-xs text-gray-500"${_scopeId}><div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"${_scopeId}></div></div></div></div></div></div></div><div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="p-6"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(ExclamationTriangleIcon), { class: "h-8 w-8 text-red-600" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-4"${_scopeId}><p class="text-sm font-medium text-gray-500"${_scopeId}>Critical Findings</p><p class="text-2xl font-semibold text-red-600"${_scopeId}>${ssrInterpolate(stats.value.criticalFindings)}</p></div></div></div></div><div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="p-6"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(HeartIcon), { class: "h-8 w-8 text-green-600" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-4"${_scopeId}><p class="text-sm font-medium text-gray-500"${_scopeId}>Avg Sentiment</p><div class="flex items-center"${_scopeId}><p class="text-2xl font-semibold text-green-600"${_scopeId}>${ssrInterpolate((stats.value.avgSentiment * 100).toFixed(0))}%</p>`);
            _push2(ssrRenderComponent(unref(ArrowTrendingUpIcon), { class: "h-4 w-4 text-green-500 ml-1" }, null, _parent2, _scopeId));
            _push2(`</div></div></div></div></div></div><div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$6, null, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$5, {
              sentiment: stats.value.avgSentiment,
              "project-count": stats.value.totalProjects,
              "analysis-count": stats.value.totalAnalyses || 156
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, null, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, null, null, _parent2, _scopeId));
            _push2(`</div><div class="grid grid-cols-1 lg:grid-cols-3 gap-8"${_scopeId}><div class="lg:col-span-2"${_scopeId}><div class="bg-white shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="px-6 py-4 border-b border-gray-200"${_scopeId}><h3 class="text-lg font-semibold text-gray-900"${_scopeId}>Recent Projects</h3><p class="text-sm text-gray-600"${_scopeId}>Latest blockchain projects under analysis</p></div><div class="divide-y divide-gray-200"${_scopeId}><!--[-->`);
            ssrRenderList(recentProjects.value, (project) => {
              _push2(`<div class="p-6 hover:bg-gray-50 transition-colors cursor-pointer"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div class="flex-1"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><h4 class="text-sm font-medium text-gray-900"${_scopeId}>${ssrInterpolate(project.name)}</h4><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"${_scopeId}>${ssrInterpolate(project.network)}</span><span class="${ssrRenderClass(["inline-flex items-center px-2 py-1 rounded-full text-xs font-medium", getRiskColor(project.riskLevel)])}"${_scopeId}>${ssrInterpolate(project.riskLevel.charAt(0).toUpperCase() + project.riskLevel.slice(1))} Risk </span></div><div class="mt-2 flex items-center space-x-4 text-sm text-gray-500"${_scopeId}><span${_scopeId}>${ssrInterpolate(project.findings)} findings</span><span${_scopeId}>•</span><div class="flex items-center"${_scopeId}>`);
              ssrRenderVNode(_push2, createVNode(resolveDynamicComponent(getSentimentIcon(project.sentiment)), {
                class: ["h-4 w-4 mr-1", getSentimentColor(project.sentiment)]
              }, null), _parent2, _scopeId);
              _push2(`<span${_scopeId}>${ssrInterpolate((project.sentiment * 100).toFixed(0))}% sentiment</span></div><span${_scopeId}>•</span><span${_scopeId}>${ssrInterpolate(project.lastAnalyzed)}</span></div></div><div class="flex items-center space-x-2"${_scopeId}>`);
              if (project.status === "analyzing") {
                _push2(`<div class="flex items-center text-blue-600"${_scopeId}><div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse mr-2"${_scopeId}></div><span class="text-xs"${_scopeId}>Analyzing</span></div>`);
              } else {
                _push2(`<div class="flex items-center text-green-600"${_scopeId}>`);
                _push2(ssrRenderComponent(unref(CheckCircleIcon), { class: "h-4 w-4 mr-1" }, null, _parent2, _scopeId));
                _push2(`<span class="text-xs"${_scopeId}>Complete</span></div>`);
              }
              _push2(`</div></div></div>`);
            });
            _push2(`<!--]--></div></div></div><div${_scopeId}><div class="bg-white shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="px-6 py-4 border-b border-gray-200"${_scopeId}><h3 class="text-lg font-semibold text-gray-900"${_scopeId}>AI Insights</h3><p class="text-sm text-gray-600"${_scopeId}>Automated recommendations</p></div><div class="p-6 space-y-4"${_scopeId}><!--[-->`);
            ssrRenderList(aiInsights.value, (insight) => {
              _push2(`<div class="border border-gray-200 rounded-lg p-4"${_scopeId}><div class="flex items-start justify-between"${_scopeId}><div class="flex-1"${_scopeId}><h4 class="text-sm font-medium text-gray-900"${_scopeId}>${ssrInterpolate(insight.title)}</h4><p class="text-xs text-gray-600 mt-1"${_scopeId}>${ssrInterpolate(insight.message)}</p><div class="mt-2 flex items-center text-xs text-gray-500"${_scopeId}><span${_scopeId}>${ssrInterpolate(insight.confidence)}% confidence</span></div></div></div><div class="mt-3"${_scopeId}><button class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-100 transition-colors"${_scopeId}>${ssrInterpolate(insight.action)}</button></div></div>`);
            });
            _push2(`<!--]--></div></div></div></div><div class="mt-8"${_scopeId}><div class="bg-white shadow-sm rounded-lg border border-gray-200"${_scopeId}><div class="px-6 py-4 border-b border-gray-200"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><h3 class="text-lg font-semibold text-gray-900"${_scopeId}>Critical Security Findings</h3><p class="text-sm text-gray-600"${_scopeId}>High-priority vulnerabilities requiring immediate attention</p></div><button class="text-sm bg-red-50 text-red-700 px-3 py-1 rounded-md hover:bg-red-100 transition-colors"${_scopeId}> View All </button></div></div><div class="overflow-hidden"${_scopeId}><table class="min-w-full divide-y divide-gray-200"${_scopeId}><thead class="bg-gray-50"${_scopeId}><tr${_scopeId}><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}>Finding</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}>Contract</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}>Severity</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}>CVSS</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}>Impact</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}>Actions</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"${_scopeId}><!--[-->`);
            ssrRenderList(criticalFindings.value, (finding) => {
              _push2(`<tr class="hover:bg-gray-50"${_scopeId}><td class="px-6 py-4"${_scopeId}><div${_scopeId}><div class="text-sm font-medium text-gray-900"${_scopeId}>${ssrInterpolate(finding.title)}</div><div class="text-sm text-gray-500"${_scopeId}>${ssrInterpolate(finding.function)}</div></div></td><td class="px-6 py-4 text-sm text-gray-900"${_scopeId}>${ssrInterpolate(finding.contract)}</td><td class="px-6 py-4"${_scopeId}><span class="${ssrRenderClass(["inline-flex items-center px-2 py-1 rounded-full text-xs font-medium", getSeverityColor(finding.severity)])}"${_scopeId}>${ssrInterpolate(finding.severity.charAt(0).toUpperCase() + finding.severity.slice(1))}</span></td><td class="px-6 py-4 text-sm font-medium text-gray-900"${_scopeId}>${ssrInterpolate(finding.cvss)}</td><td class="px-6 py-4 text-sm text-gray-900"${_scopeId}>${ssrInterpolate(finding.impact)}</td><td class="px-6 py-4 text-right text-sm"${_scopeId}><button class="text-indigo-600 hover:text-indigo-900 font-medium"${_scopeId}>View Details</button></td></tr>`);
            });
            _push2(`<!--]--></tbody></table></div></div></div><div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, null, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$1, null, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4"${_scopeId}><button class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(CpuChipIcon), { class: "h-5 w-5 mr-2" }, null, _parent2, _scopeId));
            _push2(` Start New Analysis </button><button class="bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(EyeIcon), { class: "h-5 w-5 mr-2" }, null, _parent2, _scopeId));
            _push2(` View All Projects </button><button class="bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(ShieldCheckIcon), { class: "h-5 w-5 mr-2" }, null, _parent2, _scopeId));
            _push2(` Security Report </button></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-6" }, [
                createVNode("div", { class: "mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" }, [
                    createVNode("div", { class: "bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200" }, [
                      createVNode("div", { class: "p-6" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(ChartBarIcon), { class: "h-8 w-8 text-indigo-600" })
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("p", { class: "text-sm font-medium text-gray-500" }, "Total Projects"),
                            createVNode("p", { class: "text-2xl font-semibold text-gray-900" }, toDisplayString(stats.value.totalProjects), 1)
                          ])
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200" }, [
                      createVNode("div", { class: "p-6" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(CpuChipIcon), { class: "h-8 w-8 text-blue-600" })
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("p", { class: "text-sm font-medium text-gray-500" }, "Active Analyses"),
                            createVNode("div", { class: "flex items-center" }, [
                              createVNode("p", { class: "text-2xl font-semibold text-gray-900" }, toDisplayString(stats.value.activeAnalyses), 1),
                              createVNode("div", { class: "ml-2 flex items-center text-xs text-gray-500" }, [
                                createVNode("div", { class: "w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" })
                              ])
                            ])
                          ])
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200" }, [
                      createVNode("div", { class: "p-6" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(ExclamationTriangleIcon), { class: "h-8 w-8 text-red-600" })
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("p", { class: "text-sm font-medium text-gray-500" }, "Critical Findings"),
                            createVNode("p", { class: "text-2xl font-semibold text-red-600" }, toDisplayString(stats.value.criticalFindings), 1)
                          ])
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200" }, [
                      createVNode("div", { class: "p-6" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(HeartIcon), { class: "h-8 w-8 text-green-600" })
                          ]),
                          createVNode("div", { class: "ml-4" }, [
                            createVNode("p", { class: "text-sm font-medium text-gray-500" }, "Avg Sentiment"),
                            createVNode("div", { class: "flex items-center" }, [
                              createVNode("p", { class: "text-2xl font-semibold text-green-600" }, toDisplayString((stats.value.avgSentiment * 100).toFixed(0)) + "%", 1),
                              createVNode(unref(ArrowTrendingUpIcon), { class: "h-4 w-4 text-green-500 ml-1" })
                            ])
                          ])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8" }, [
                    createVNode(_sfc_main$6),
                    createVNode(_sfc_main$5, {
                      sentiment: stats.value.avgSentiment,
                      "project-count": stats.value.totalProjects,
                      "analysis-count": stats.value.totalAnalyses || 156
                    }, null, 8, ["sentiment", "project-count", "analysis-count"])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8" }, [
                    createVNode(_sfc_main$4),
                    createVNode(_sfc_main$3)
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-3 gap-8" }, [
                    createVNode("div", { class: "lg:col-span-2" }, [
                      createVNode("div", { class: "bg-white shadow-sm rounded-lg border border-gray-200" }, [
                        createVNode("div", { class: "px-6 py-4 border-b border-gray-200" }, [
                          createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, "Recent Projects"),
                          createVNode("p", { class: "text-sm text-gray-600" }, "Latest blockchain projects under analysis")
                        ]),
                        createVNode("div", { class: "divide-y divide-gray-200" }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(recentProjects.value, (project) => {
                            return openBlock(), createBlock("div", {
                              key: project.id,
                              class: "p-6 hover:bg-gray-50 transition-colors cursor-pointer"
                            }, [
                              createVNode("div", { class: "flex items-center justify-between" }, [
                                createVNode("div", { class: "flex-1" }, [
                                  createVNode("div", { class: "flex items-center space-x-3" }, [
                                    createVNode("h4", { class: "text-sm font-medium text-gray-900" }, toDisplayString(project.name), 1),
                                    createVNode("span", { class: "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800" }, toDisplayString(project.network), 1),
                                    createVNode("span", {
                                      class: ["inline-flex items-center px-2 py-1 rounded-full text-xs font-medium", getRiskColor(project.riskLevel)]
                                    }, toDisplayString(project.riskLevel.charAt(0).toUpperCase() + project.riskLevel.slice(1)) + " Risk ", 3)
                                  ]),
                                  createVNode("div", { class: "mt-2 flex items-center space-x-4 text-sm text-gray-500" }, [
                                    createVNode("span", null, toDisplayString(project.findings) + " findings", 1),
                                    createVNode("span", null, "•"),
                                    createVNode("div", { class: "flex items-center" }, [
                                      (openBlock(), createBlock(resolveDynamicComponent(getSentimentIcon(project.sentiment)), {
                                        class: ["h-4 w-4 mr-1", getSentimentColor(project.sentiment)]
                                      }, null, 8, ["class"])),
                                      createVNode("span", null, toDisplayString((project.sentiment * 100).toFixed(0)) + "% sentiment", 1)
                                    ]),
                                    createVNode("span", null, "•"),
                                    createVNode("span", null, toDisplayString(project.lastAnalyzed), 1)
                                  ])
                                ]),
                                createVNode("div", { class: "flex items-center space-x-2" }, [
                                  project.status === "analyzing" ? (openBlock(), createBlock("div", {
                                    key: 0,
                                    class: "flex items-center text-blue-600"
                                  }, [
                                    createVNode("div", { class: "w-2 h-2 bg-blue-600 rounded-full animate-pulse mr-2" }),
                                    createVNode("span", { class: "text-xs" }, "Analyzing")
                                  ])) : (openBlock(), createBlock("div", {
                                    key: 1,
                                    class: "flex items-center text-green-600"
                                  }, [
                                    createVNode(unref(CheckCircleIcon), { class: "h-4 w-4 mr-1" }),
                                    createVNode("span", { class: "text-xs" }, "Complete")
                                  ]))
                                ])
                              ])
                            ]);
                          }), 128))
                        ])
                      ])
                    ]),
                    createVNode("div", null, [
                      createVNode("div", { class: "bg-white shadow-sm rounded-lg border border-gray-200" }, [
                        createVNode("div", { class: "px-6 py-4 border-b border-gray-200" }, [
                          createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, "AI Insights"),
                          createVNode("p", { class: "text-sm text-gray-600" }, "Automated recommendations")
                        ]),
                        createVNode("div", { class: "p-6 space-y-4" }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(aiInsights.value, (insight) => {
                            return openBlock(), createBlock("div", {
                              key: insight.type,
                              class: "border border-gray-200 rounded-lg p-4"
                            }, [
                              createVNode("div", { class: "flex items-start justify-between" }, [
                                createVNode("div", { class: "flex-1" }, [
                                  createVNode("h4", { class: "text-sm font-medium text-gray-900" }, toDisplayString(insight.title), 1),
                                  createVNode("p", { class: "text-xs text-gray-600 mt-1" }, toDisplayString(insight.message), 1),
                                  createVNode("div", { class: "mt-2 flex items-center text-xs text-gray-500" }, [
                                    createVNode("span", null, toDisplayString(insight.confidence) + "% confidence", 1)
                                  ])
                                ])
                              ]),
                              createVNode("div", { class: "mt-3" }, [
                                createVNode("button", { class: "text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-100 transition-colors" }, toDisplayString(insight.action), 1)
                              ])
                            ]);
                          }), 128))
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "mt-8" }, [
                    createVNode("div", { class: "bg-white shadow-sm rounded-lg border border-gray-200" }, [
                      createVNode("div", { class: "px-6 py-4 border-b border-gray-200" }, [
                        createVNode("div", { class: "flex items-center justify-between" }, [
                          createVNode("div", null, [
                            createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, "Critical Security Findings"),
                            createVNode("p", { class: "text-sm text-gray-600" }, "High-priority vulnerabilities requiring immediate attention")
                          ]),
                          createVNode("button", { class: "text-sm bg-red-50 text-red-700 px-3 py-1 rounded-md hover:bg-red-100 transition-colors" }, " View All ")
                        ])
                      ]),
                      createVNode("div", { class: "overflow-hidden" }, [
                        createVNode("table", { class: "min-w-full divide-y divide-gray-200" }, [
                          createVNode("thead", { class: "bg-gray-50" }, [
                            createVNode("tr", null, [
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, "Finding"),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, "Contract"),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, "Severity"),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, "CVSS"),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, "Impact"),
                              createVNode("th", { class: "px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider" }, "Actions")
                            ])
                          ]),
                          createVNode("tbody", { class: "bg-white divide-y divide-gray-200" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(criticalFindings.value, (finding) => {
                              return openBlock(), createBlock("tr", {
                                key: finding.id,
                                class: "hover:bg-gray-50"
                              }, [
                                createVNode("td", { class: "px-6 py-4" }, [
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-sm font-medium text-gray-900" }, toDisplayString(finding.title), 1),
                                    createVNode("div", { class: "text-sm text-gray-500" }, toDisplayString(finding.function), 1)
                                  ])
                                ]),
                                createVNode("td", { class: "px-6 py-4 text-sm text-gray-900" }, toDisplayString(finding.contract), 1),
                                createVNode("td", { class: "px-6 py-4" }, [
                                  createVNode("span", {
                                    class: ["inline-flex items-center px-2 py-1 rounded-full text-xs font-medium", getSeverityColor(finding.severity)]
                                  }, toDisplayString(finding.severity.charAt(0).toUpperCase() + finding.severity.slice(1)), 3)
                                ]),
                                createVNode("td", { class: "px-6 py-4 text-sm font-medium text-gray-900" }, toDisplayString(finding.cvss), 1),
                                createVNode("td", { class: "px-6 py-4 text-sm text-gray-900" }, toDisplayString(finding.impact), 1),
                                createVNode("td", { class: "px-6 py-4 text-right text-sm" }, [
                                  createVNode("button", { class: "text-indigo-600 hover:text-indigo-900 font-medium" }, "View Details")
                                ])
                              ]);
                            }), 128))
                          ])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8" }, [
                    createVNode(_sfc_main$2),
                    createVNode(_sfc_main$1)
                  ]),
                  createVNode("div", { class: "mt-8 grid grid-cols-1 md:grid-cols-3 gap-4" }, [
                    createVNode("button", { class: "bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center" }, [
                      createVNode(unref(CpuChipIcon), { class: "h-5 w-5 mr-2" }),
                      createTextVNode(" Start New Analysis ")
                    ]),
                    createVNode("button", { class: "bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center" }, [
                      createVNode(unref(EyeIcon), { class: "h-5 w-5 mr-2" }),
                      createTextVNode(" View All Projects ")
                    ]),
                    createVNode("button", { class: "bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center" }, [
                      createVNode(unref(ShieldCheckIcon), { class: "h-5 w-5 mr-2" }),
                      createTextVNode(" Security Report ")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Dashboard.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
