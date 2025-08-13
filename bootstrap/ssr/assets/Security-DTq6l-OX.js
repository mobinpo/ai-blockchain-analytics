import { ref, computed, withCtx, unref, createVNode, createBlock, createCommentVNode, toDisplayString, withDirectives, openBlock, Fragment, renderList, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderClass } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./AppLayout-UOYGqPAE.js";
import "@inertiajs/vue3";
const _sfc_main = {
  __name: "Security",
  __ssrInlineRender: true,
  setup(__props) {
    const selectedSeverity = ref("all");
    const selectedProject = ref("all");
    const findings = ref([
      {
        id: 1,
        title: "Reentrancy Vulnerability in Transfer Function",
        severity: "critical",
        project: "Uniswap V4",
        description: "The transfer function allows for reentrancy attacks due to external calls before state changes.",
        line: 127,
        file: "UniswapV4Pool.sol",
        impact: "High - Could lead to complete draining of pool funds",
        recommendation: "Implement reentrancy guard or follow checks-effects-interactions pattern",
        detectedAt: "5 minutes ago",
        status: "open",
        codeSnippet: `function transfer(address to, uint256 amount) external {
    require(balances[msg.sender] >= amount, "Insufficient balance");
    // VULNERABILITY: External call before state change
    IERC20(token).transfer(to, amount);
    balances[msg.sender] -= amount; // State change after external call
}`
      },
      {
        id: 2,
        title: "Unchecked Return Value",
        severity: "high",
        project: "Compound Gov",
        description: "External call return value is not checked, potentially leading to silent failures.",
        line: 89,
        file: "Governance.sol",
        impact: "Medium - Governance proposals may fail silently",
        recommendation: "Always check return values of external calls or use SafeERC20",
        detectedAt: "1 hour ago",
        status: "open",
        codeSnippet: `function executeProposal(uint256 proposalId) external {
    Proposal storage proposal = proposals[proposalId];
    // VULNERABILITY: Return value not checked
    proposal.target.call(proposal.data);
    proposal.executed = true;
}`
      },
      {
        id: 3,
        title: "Integer Overflow in Calculation",
        severity: "high",
        project: "Uniswap V4",
        description: "Arithmetic operation may overflow without proper checks.",
        line: 245,
        file: "Math.sol",
        impact: "High - Incorrect calculations could affect trading",
        recommendation: "Use SafeMath library or Solidity 0.8+ built-in overflow checks",
        detectedAt: "2 hours ago",
        status: "investigating",
        codeSnippet: `function calculatePrice(uint256 amount0, uint256 amount1) pure returns (uint256) {
    // VULNERABILITY: Potential overflow
    return (amount0 * 1e18) / amount1;
}`
      },
      {
        id: 4,
        title: "Gas Optimization Opportunity",
        severity: "medium",
        project: "Aave V3 Lending",
        description: "Loop iteration could be optimized to reduce gas consumption.",
        line: 156,
        file: "LendingPool.sol",
        impact: "Low - Higher gas costs for users",
        recommendation: "Consider batching operations or using more efficient data structures",
        detectedAt: "3 hours ago",
        status: "resolved",
        codeSnippet: `function updateReserves() external {
    for (uint256 i = 0; i < reserves.length; i++) {
        // OPTIMIZATION: This loop could be gas-intensive for many reserves
        _updateReserveInterestRates(reserves[i]);
    }
}`
      },
      {
        id: 5,
        title: "Missing Input Validation",
        severity: "medium",
        project: "Compound Gov",
        description: "Function parameters are not properly validated before use.",
        line: 78,
        file: "Timelock.sol",
        impact: "Medium - Could lead to unexpected behavior",
        recommendation: "Add proper input validation and boundary checks",
        detectedAt: "4 hours ago",
        status: "open",
        codeSnippet: `function setDelay(uint256 delay_) external {
    // VULNERABILITY: No validation on delay_ parameter
    delay = delay_;
    emit DelayChanged(delay_);
}`
      }
    ]);
    const projects = ["all", "Uniswap V4", "Aave V3 Lending", "Compound Gov"];
    const severities = ["all", "critical", "high", "medium", "low"];
    const filteredFindings = computed(() => {
      return findings.value.filter((finding) => {
        const severityMatch = selectedSeverity.value === "all" || finding.severity === selectedSeverity.value;
        const projectMatch = selectedProject.value === "all" || finding.project === selectedProject.value;
        return severityMatch && projectMatch;
      });
    });
    const getSeverityColor = (severity) => {
      const colors = {
        critical: "text-red-600 bg-red-50 border-red-200",
        high: "text-orange-600 bg-orange-50 border-orange-200",
        medium: "text-yellow-600 bg-yellow-50 border-yellow-200",
        low: "text-blue-600 bg-blue-50 border-blue-200"
      };
      return colors[severity] || "text-gray-600 bg-gray-50 border-gray-200";
    };
    const getStatusColor = (status) => {
      const colors = {
        open: "text-red-600 bg-red-50",
        investigating: "text-yellow-600 bg-yellow-50",
        resolved: "text-green-600 bg-green-50"
      };
      return colors[status] || "text-gray-600 bg-gray-50";
    };
    const getSeverityStats = () => {
      const stats = { critical: 0, high: 0, medium: 0, low: 0 };
      findings.value.forEach((finding) => {
        stats[finding.severity]++;
      });
      return stats;
    };
    const severityStats = getSeverityStats();
    ref(null);
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="mb-8"${_scopeId}><h1 class="text-3xl font-bold text-gray-900 mb-2"${_scopeId}>Security Analysis</h1><p class="text-gray-600"${_scopeId}>Comprehensive smart contract vulnerability detection and analysis</p></div><div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8"${_scopeId}><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><p class="text-sm text-gray-600 mb-1"${_scopeId}>Critical Issues</p><p class="text-2xl font-bold text-red-600"${_scopeId}>${ssrInterpolate(unref(severityStats).critical)}</p></div><div class="text-3xl"${_scopeId}>🚨</div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><p class="text-sm text-gray-600 mb-1"${_scopeId}>High Issues</p><p class="text-2xl font-bold text-orange-600"${_scopeId}>${ssrInterpolate(unref(severityStats).high)}</p></div><div class="text-3xl"${_scopeId}>⚠️</div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><p class="text-sm text-gray-600 mb-1"${_scopeId}>Medium Issues</p><p class="text-2xl font-bold text-yellow-600"${_scopeId}>${ssrInterpolate(unref(severityStats).medium)}</p></div><div class="text-3xl"${_scopeId}>⚡</div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><p class="text-sm text-gray-600 mb-1"${_scopeId}>Total Scanned</p><p class="text-2xl font-bold text-gray-800"${_scopeId}>${ssrInterpolate(findings.value.length)}</p></div><div class="text-3xl"${_scopeId}>🔍</div></div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8"${_scopeId}><div class="flex flex-wrap items-center gap-4"${_scopeId}><div${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-2"${_scopeId}>Severity</label><select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"${_scopeId}><!--[-->`);
            ssrRenderList(severities, (severity) => {
              _push2(`<option${ssrRenderAttr("value", severity)}${ssrIncludeBooleanAttr(Array.isArray(selectedSeverity.value) ? ssrLooseContain(selectedSeverity.value, severity) : ssrLooseEqual(selectedSeverity.value, severity)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(severity === "all" ? "All Severities" : severity.charAt(0).toUpperCase() + severity.slice(1))}</option>`);
            });
            _push2(`<!--]--></select></div><div${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-2"${_scopeId}>Project</label><select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"${_scopeId}><!--[-->`);
            ssrRenderList(projects, (project) => {
              _push2(`<option${ssrRenderAttr("value", project)}${ssrIncludeBooleanAttr(Array.isArray(selectedProject.value) ? ssrLooseContain(selectedProject.value, project) : ssrLooseEqual(selectedProject.value, project)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(project === "all" ? "All Projects" : project)}</option>`);
            });
            _push2(`<!--]--></select></div><div class="flex-1"${_scopeId}></div><button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium"${_scopeId}> Export Report </button></div></div><div class="space-y-4"${_scopeId}><!--[-->`);
            ssrRenderList(filteredFindings.value, (finding) => {
              _push2(`<div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"${_scopeId}><div class="p-6"${_scopeId}><div class="flex items-start justify-between mb-4"${_scopeId}><div class="flex-1"${_scopeId}><div class="flex items-center space-x-3 mb-2"${_scopeId}><h3 class="text-lg font-semibold text-gray-900"${_scopeId}>${ssrInterpolate(finding.title)}</h3><span class="${ssrRenderClass([getSeverityColor(finding.severity), "px-3 py-1 text-xs font-medium rounded-full border"])}"${_scopeId}>${ssrInterpolate(finding.severity.toUpperCase())}</span><span class="${ssrRenderClass([getStatusColor(finding.status), "px-3 py-1 text-xs font-medium rounded-full"])}"${_scopeId}>${ssrInterpolate(finding.status.toUpperCase())}</span></div><div class="flex items-center space-x-6 text-sm text-gray-600 mb-3"${_scopeId}><span${_scopeId}>📁 ${ssrInterpolate(finding.project)}</span><span${_scopeId}>📄 ${ssrInterpolate(finding.file)}:${ssrInterpolate(finding.line)}</span><span${_scopeId}>🕒 ${ssrInterpolate(finding.detectedAt)}</span></div><p class="text-gray-700 mb-4"${_scopeId}>${ssrInterpolate(finding.description)}</p></div></div><div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6"${_scopeId}><div class="space-y-4"${_scopeId}><div${_scopeId}><h4 class="text-sm font-semibold text-gray-900 mb-2"${_scopeId}>Impact</h4><p class="text-sm text-gray-700 bg-red-50 p-3 rounded-md"${_scopeId}>${ssrInterpolate(finding.impact)}</p></div><div${_scopeId}><h4 class="text-sm font-semibold text-gray-900 mb-2"${_scopeId}>Recommendation</h4><p class="text-sm text-gray-700 bg-green-50 p-3 rounded-md"${_scopeId}>${ssrInterpolate(finding.recommendation)}</p></div></div><div${_scopeId}><h4 class="text-sm font-semibold text-gray-900 mb-2"${_scopeId}>Code Snippet</h4><div class="bg-gray-900 text-gray-100 p-4 rounded-md text-xs font-mono overflow-x-auto"${_scopeId}><pre${_scopeId}>${ssrInterpolate(finding.codeSnippet)}</pre></div></div></div><div class="flex items-center space-x-3 pt-4 border-t border-gray-200"${_scopeId}><button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium"${_scopeId}> View Full Details </button>`);
              if (finding.status === "open") {
                _push2(`<button class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-md hover:bg-yellow-200 transition-colors text-sm font-medium"${_scopeId}> Mark as Investigating </button>`);
              } else {
                _push2(`<!---->`);
              }
              if (finding.status !== "resolved") {
                _push2(`<button class="px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors text-sm font-medium"${_scopeId}> Mark as Resolved </button>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium"${_scopeId}> Add Comment </button></div></div></div>`);
            });
            _push2(`<!--]--></div>`);
            if (filteredFindings.value.length === 0) {
              _push2(`<div class="text-center py-12"${_scopeId}><div class="text-6xl mb-4"${_scopeId}>🔍</div><h3 class="text-lg font-medium text-gray-900 mb-2"${_scopeId}>No findings match your filters</h3><p class="text-gray-600 mb-6"${_scopeId}>Try adjusting your severity or project filters to see more results.</p><button class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors"${_scopeId}> Clear Filters </button></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="mt-8 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg p-6 text-white"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><div${_scopeId}><h3 class="text-lg font-semibold mb-2"${_scopeId}>🔬 AI-Powered Security Analysis</h3><p class="text-purple-100"${_scopeId}>Our advanced AI models detect complex vulnerabilities that traditional tools miss, providing comprehensive smart contract security analysis.</p></div><button class="px-6 py-3 bg-white bg-opacity-20 rounded-lg font-medium hover:bg-opacity-30 transition-colors"${_scopeId}> Learn More </button></div></div>`);
          } else {
            return [
              createVNode("div", { class: "mb-8" }, [
                createVNode("h1", { class: "text-3xl font-bold text-gray-900 mb-2" }, "Security Analysis"),
                createVNode("p", { class: "text-gray-600" }, "Comprehensive smart contract vulnerability detection and analysis")
              ]),
              createVNode("div", { class: "grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" }, [
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Critical Issues"),
                      createVNode("p", { class: "text-2xl font-bold text-red-600" }, toDisplayString(unref(severityStats).critical), 1)
                    ]),
                    createVNode("div", { class: "text-3xl" }, "🚨")
                  ])
                ]),
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "High Issues"),
                      createVNode("p", { class: "text-2xl font-bold text-orange-600" }, toDisplayString(unref(severityStats).high), 1)
                    ]),
                    createVNode("div", { class: "text-3xl" }, "⚠️")
                  ])
                ]),
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Medium Issues"),
                      createVNode("p", { class: "text-2xl font-bold text-yellow-600" }, toDisplayString(unref(severityStats).medium), 1)
                    ]),
                    createVNode("div", { class: "text-3xl" }, "⚡")
                  ])
                ]),
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Total Scanned"),
                      createVNode("p", { class: "text-2xl font-bold text-gray-800" }, toDisplayString(findings.value.length), 1)
                    ]),
                    createVNode("div", { class: "text-3xl" }, "🔍")
                  ])
                ])
              ]),
              createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8" }, [
                createVNode("div", { class: "flex flex-wrap items-center gap-4" }, [
                  createVNode("div", null, [
                    createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, "Severity"),
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => selectedSeverity.value = $event,
                      class: "px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    }, [
                      (openBlock(), createBlock(Fragment, null, renderList(severities, (severity) => {
                        return createVNode("option", {
                          key: severity,
                          value: severity
                        }, toDisplayString(severity === "all" ? "All Severities" : severity.charAt(0).toUpperCase() + severity.slice(1)), 9, ["value"]);
                      }), 64))
                    ], 8, ["onUpdate:modelValue"]), [
                      [vModelSelect, selectedSeverity.value]
                    ])
                  ]),
                  createVNode("div", null, [
                    createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, "Project"),
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => selectedProject.value = $event,
                      class: "px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    }, [
                      (openBlock(), createBlock(Fragment, null, renderList(projects, (project) => {
                        return createVNode("option", {
                          key: project,
                          value: project
                        }, toDisplayString(project === "all" ? "All Projects" : project), 9, ["value"]);
                      }), 64))
                    ], 8, ["onUpdate:modelValue"]), [
                      [vModelSelect, selectedProject.value]
                    ])
                  ]),
                  createVNode("div", { class: "flex-1" }),
                  createVNode("button", { class: "px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium" }, " Export Report ")
                ])
              ]),
              createVNode("div", { class: "space-y-4" }, [
                (openBlock(true), createBlock(Fragment, null, renderList(filteredFindings.value, (finding) => {
                  return openBlock(), createBlock("div", {
                    key: finding.id,
                    class: "bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                  }, [
                    createVNode("div", { class: "p-6" }, [
                      createVNode("div", { class: "flex items-start justify-between mb-4" }, [
                        createVNode("div", { class: "flex-1" }, [
                          createVNode("div", { class: "flex items-center space-x-3 mb-2" }, [
                            createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, toDisplayString(finding.title), 1),
                            createVNode("span", {
                              class: [getSeverityColor(finding.severity), "px-3 py-1 text-xs font-medium rounded-full border"]
                            }, toDisplayString(finding.severity.toUpperCase()), 3),
                            createVNode("span", {
                              class: [getStatusColor(finding.status), "px-3 py-1 text-xs font-medium rounded-full"]
                            }, toDisplayString(finding.status.toUpperCase()), 3)
                          ]),
                          createVNode("div", { class: "flex items-center space-x-6 text-sm text-gray-600 mb-3" }, [
                            createVNode("span", null, "📁 " + toDisplayString(finding.project), 1),
                            createVNode("span", null, "📄 " + toDisplayString(finding.file) + ":" + toDisplayString(finding.line), 1),
                            createVNode("span", null, "🕒 " + toDisplayString(finding.detectedAt), 1)
                          ]),
                          createVNode("p", { class: "text-gray-700 mb-4" }, toDisplayString(finding.description), 1)
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" }, [
                        createVNode("div", { class: "space-y-4" }, [
                          createVNode("div", null, [
                            createVNode("h4", { class: "text-sm font-semibold text-gray-900 mb-2" }, "Impact"),
                            createVNode("p", { class: "text-sm text-gray-700 bg-red-50 p-3 rounded-md" }, toDisplayString(finding.impact), 1)
                          ]),
                          createVNode("div", null, [
                            createVNode("h4", { class: "text-sm font-semibold text-gray-900 mb-2" }, "Recommendation"),
                            createVNode("p", { class: "text-sm text-gray-700 bg-green-50 p-3 rounded-md" }, toDisplayString(finding.recommendation), 1)
                          ])
                        ]),
                        createVNode("div", null, [
                          createVNode("h4", { class: "text-sm font-semibold text-gray-900 mb-2" }, "Code Snippet"),
                          createVNode("div", { class: "bg-gray-900 text-gray-100 p-4 rounded-md text-xs font-mono overflow-x-auto" }, [
                            createVNode("pre", null, toDisplayString(finding.codeSnippet), 1)
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "flex items-center space-x-3 pt-4 border-t border-gray-200" }, [
                        createVNode("button", { class: "px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium" }, " View Full Details "),
                        finding.status === "open" ? (openBlock(), createBlock("button", {
                          key: 0,
                          class: "px-4 py-2 bg-yellow-100 text-yellow-700 rounded-md hover:bg-yellow-200 transition-colors text-sm font-medium"
                        }, " Mark as Investigating ")) : createCommentVNode("", true),
                        finding.status !== "resolved" ? (openBlock(), createBlock("button", {
                          key: 1,
                          class: "px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors text-sm font-medium"
                        }, " Mark as Resolved ")) : createCommentVNode("", true),
                        createVNode("button", { class: "px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium" }, " Add Comment ")
                      ])
                    ])
                  ]);
                }), 128))
              ]),
              filteredFindings.value.length === 0 ? (openBlock(), createBlock("div", {
                key: 0,
                class: "text-center py-12"
              }, [
                createVNode("div", { class: "text-6xl mb-4" }, "🔍"),
                createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-2" }, "No findings match your filters"),
                createVNode("p", { class: "text-gray-600 mb-6" }, "Try adjusting your severity or project filters to see more results."),
                createVNode("button", {
                  onClick: ($event) => {
                    selectedSeverity.value = "all";
                    selectedProject.value = "all";
                  },
                  class: "px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors"
                }, " Clear Filters ", 8, ["onClick"])
              ])) : createCommentVNode("", true),
              createVNode("div", { class: "mt-8 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg p-6 text-white" }, [
                createVNode("div", { class: "flex items-center justify-between" }, [
                  createVNode("div", null, [
                    createVNode("h3", { class: "text-lg font-semibold mb-2" }, "🔬 AI-Powered Security Analysis"),
                    createVNode("p", { class: "text-purple-100" }, "Our advanced AI models detect complex vulnerabilities that traditional tools miss, providing comprehensive smart contract security analysis.")
                  ]),
                  createVNode("button", { class: "px-6 py-3 bg-white bg-opacity-20 rounded-lg font-medium hover:bg-opacity-30 transition-colors" }, " Learn More ")
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Security.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
