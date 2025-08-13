import { ref, onMounted, onUnmounted, withCtx, createVNode, createBlock, createCommentVNode, openBlock, Fragment, renderList, toDisplayString, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderClass, ssrRenderStyle } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./AppLayout-UOYGqPAE.js";
import "@inertiajs/vue3";
const _sfc_main = {
  __name: "Projects",
  __ssrInlineRender: true,
  setup(__props) {
    const projects = ref([
      {
        id: 1,
        name: "Uniswap V4 Core",
        description: "Next-generation AMM with hooks and concentrated liquidity",
        status: "analyzing",
        risk: "medium",
        progress: 75,
        lastAnalysis: "2 minutes ago",
        criticalIssues: 2,
        highIssues: 5,
        mediumIssues: 8,
        lowIssues: 12,
        sentiment: 0.73,
        contractAddress: "0x1f9840a85d5aF5bf1D1762F925BDADdC4201F984",
        network: "Ethereum Mainnet"
      },
      {
        id: 2,
        name: "Aave V3 Lending Pool",
        description: "Multi-collateral lending protocol with enhanced capital efficiency",
        status: "completed",
        risk: "low",
        progress: 100,
        lastAnalysis: "1 hour ago",
        criticalIssues: 0,
        highIssues: 1,
        mediumIssues: 2,
        lowIssues: 5,
        sentiment: 0.85,
        contractAddress: "0x7Fc66500c84A76Ad7e9c93437bFc5Ac33E2DDaE9",
        network: "Ethereum Mainnet"
      },
      {
        id: 3,
        name: "Compound Governance",
        description: "Decentralized governance system for protocol upgrades",
        status: "completed",
        risk: "high",
        progress: 100,
        lastAnalysis: "3 hours ago",
        criticalIssues: 5,
        highIssues: 8,
        mediumIssues: 10,
        lowIssues: 15,
        sentiment: 0.42,
        contractAddress: "0xc00e94Cb662C3520282E6f5717214004A7f26888",
        network: "Ethereum Mainnet"
      },
      {
        id: 4,
        name: "MakerDAO Multi-Collateral",
        description: "Stablecoin generation system with multiple collateral types",
        status: "pending",
        risk: "medium",
        progress: 0,
        lastAnalysis: "Never",
        criticalIssues: 0,
        highIssues: 0,
        mediumIssues: 0,
        lowIssues: 0,
        sentiment: 0.68,
        contractAddress: "0x9f8F72aA9304c8B593d555F12eF6589cC3A579A2",
        network: "Ethereum Mainnet"
      }
    ]);
    const showNewProjectModal = ref(false);
    ref(null);
    const getRiskColor = (risk) => {
      const colors = {
        low: "text-green-600 bg-green-50 border-green-200",
        medium: "text-yellow-600 bg-yellow-50 border-yellow-200",
        high: "text-red-600 bg-red-50 border-red-200"
      };
      return colors[risk] || "text-gray-600 bg-gray-50 border-gray-200";
    };
    const getStatusColor = (status) => {
      const colors = {
        analyzing: "text-blue-600 bg-blue-50 border-blue-200",
        completed: "text-green-600 bg-green-50 border-green-200",
        pending: "text-gray-600 bg-gray-50 border-gray-200",
        failed: "text-red-600 bg-red-50 border-red-200"
      };
      return colors[status] || "text-gray-600 bg-gray-50 border-gray-200";
    };
    const getTotalIssues = (project) => {
      return project.criticalIssues + project.highIssues + project.mediumIssues + project.lowIssues;
    };
    const analysisIntervals = /* @__PURE__ */ new Map();
    const isComponentActive = ref(true);
    const startAnalysis = async (project) => {
      try {
        if (analysisIntervals.has(project.id)) {
          console.log(`⚠️ Analysis already running for project ${project.id}`);
          return;
        }
        console.log(`🚀 Starting analysis for project: ${project.name}`);
        project.status = "analyzing";
        project.progress = 0;
        const analysisPromise = new Promise((resolve, reject) => {
          const interval = setInterval(() => {
            try {
              if (!isComponentActive.value) {
                console.log("⏹️ Component inactive, stopping analysis");
                clearInterval(interval);
                analysisIntervals.delete(project.id);
                reject(new Error("Component unmounted during analysis"));
                return;
              }
              const progressIncrement = Math.random() * 10;
              project.progress = Math.min(100, project.progress + progressIncrement);
              if (Math.random() < 0.05 && project.progress > 20) {
                throw new Error("Analysis service temporarily unavailable");
              }
              if (project.progress >= 100) {
                project.progress = 100;
                project.status = "completed";
                project.lastAnalysis = "Just now";
                if (Math.random() > 0.5) {
                  project.criticalIssues += Math.floor(Math.random() * 2);
                  project.highIssues += Math.floor(Math.random() * 3);
                }
                clearInterval(interval);
                analysisIntervals.delete(project.id);
                console.log(`✅ Analysis completed for project: ${project.name}`);
                resolve(project);
              }
            } catch (error) {
              console.error(`❌ Analysis error for project ${project.name}:`, error);
              project.status = "failed";
              project.progress = 0;
              clearInterval(interval);
              analysisIntervals.delete(project.id);
              reject(error);
            }
          }, 500);
          analysisIntervals.set(project.id, interval);
          setTimeout(() => {
            if (analysisIntervals.has(project.id)) {
              console.error(`⏰ Analysis timeout for project: ${project.name}`);
              project.status = "failed";
              project.progress = 0;
              clearInterval(interval);
              analysisIntervals.delete(project.id);
              reject(new Error("Analysis timed out after 60 seconds"));
            }
          }, 6e4);
        });
        try {
          await analysisPromise;
        } catch (error) {
          console.error(`🚨 Analysis failed for project ${project.name}:`, error);
          if (error.message.includes("temporarily unavailable")) {
            console.log(`🔄 Retrying analysis for ${project.name} in 5 seconds...`);
            setTimeout(() => {
              if (isComponentActive.value) {
                startAnalysis(project);
              }
            }, 5e3);
          }
        }
      } catch (error) {
        console.error(`💥 Failed to start analysis for project ${project.name}:`, error);
        project.status = "failed";
        project.progress = 0;
      }
    };
    const cleanupAnalyses = () => {
      console.log("🧹 Cleaning up running analyses...");
      analysisIntervals.forEach((interval, projectId) => {
        clearInterval(interval);
        console.log(`⏹️ Stopped analysis for project ${projectId}`);
      });
      analysisIntervals.clear();
    };
    onMounted(() => {
      console.log("📋 Projects component mounted");
      isComponentActive.value = true;
    });
    onUnmounted(() => {
      console.log("🧹 Projects component unmounting...");
      isComponentActive.value = false;
      cleanupAnalyses();
      console.log("✅ Projects cleanup completed");
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="flex items-center justify-between mb-8"${_scopeId}><div${_scopeId}><h1 class="text-3xl font-bold text-gray-900 mb-2"${_scopeId}>Projects</h1><p class="text-gray-600"${_scopeId}>Manage and monitor your blockchain security analysis projects</p></div><button class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors flex items-center space-x-2"${_scopeId}><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg><span${_scopeId}>New Project</span></button></div><div class="grid grid-cols-1 lg:grid-cols-2 gap-6"${_scopeId}><!--[-->`);
            ssrRenderList(projects.value, (project) => {
              _push2(`<div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"${_scopeId}><div class="p-6 border-b border-gray-200"${_scopeId}><div class="flex items-start justify-between mb-4"${_scopeId}><div class="flex-1"${_scopeId}><h3 class="text-lg font-semibold text-gray-900 mb-1"${_scopeId}>${ssrInterpolate(project.name)}</h3><p class="text-sm text-gray-600 mb-3"${_scopeId}>${ssrInterpolate(project.description)}</p><div class="flex items-center space-x-4 text-xs text-gray-500"${_scopeId}><span${_scopeId}>📍 ${ssrInterpolate(project.network)}</span><span${_scopeId}>📄 ${ssrInterpolate(project.contractAddress.slice(0, 10))}...</span></div></div><div class="flex items-center space-x-2"${_scopeId}><span class="${ssrRenderClass([getRiskColor(project.risk), "px-3 py-1 text-xs font-medium rounded-full border"])}"${_scopeId}>${ssrInterpolate(project.risk.toUpperCase())} RISK </span><span class="${ssrRenderClass([getStatusColor(project.status), "px-3 py-1 text-xs font-medium rounded-full border"])}"${_scopeId}>${ssrInterpolate(project.status.toUpperCase())}</span></div></div>`);
              if (project.status === "analyzing") {
                _push2(`<div class="mb-4"${_scopeId}><div class="flex items-center justify-between text-sm text-gray-600 mb-1"${_scopeId}><span${_scopeId}>Analysis Progress</span><span${_scopeId}>${ssrInterpolate(Math.round(project.progress))}%</span></div><div class="w-full bg-gray-200 rounded-full h-2"${_scopeId}><div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="${ssrRenderStyle({ width: project.progress + "%" })}"${_scopeId}></div></div></div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div><div class="p-6"${_scopeId}><div class="grid grid-cols-2 gap-6 mb-6"${_scopeId}><div${_scopeId}><h4 class="text-sm font-medium text-gray-900 mb-3"${_scopeId}>Security Issues</h4><div class="space-y-2"${_scopeId}><div class="flex items-center justify-between text-sm"${_scopeId}><div class="flex items-center space-x-2"${_scopeId}><div class="w-3 h-3 bg-red-500 rounded-full"${_scopeId}></div><span${_scopeId}>Critical</span></div><span class="font-medium"${_scopeId}>${ssrInterpolate(project.criticalIssues)}</span></div><div class="flex items-center justify-between text-sm"${_scopeId}><div class="flex items-center space-x-2"${_scopeId}><div class="w-3 h-3 bg-orange-500 rounded-full"${_scopeId}></div><span${_scopeId}>High</span></div><span class="font-medium"${_scopeId}>${ssrInterpolate(project.highIssues)}</span></div><div class="flex items-center justify-between text-sm"${_scopeId}><div class="flex items-center space-x-2"${_scopeId}><div class="w-3 h-3 bg-yellow-500 rounded-full"${_scopeId}></div><span${_scopeId}>Medium</span></div><span class="font-medium"${_scopeId}>${ssrInterpolate(project.mediumIssues)}</span></div><div class="flex items-center justify-between text-sm"${_scopeId}><div class="flex items-center space-x-2"${_scopeId}><div class="w-3 h-3 bg-blue-500 rounded-full"${_scopeId}></div><span${_scopeId}>Low</span></div><span class="font-medium"${_scopeId}>${ssrInterpolate(project.lowIssues)}</span></div></div></div><div${_scopeId}><h4 class="text-sm font-medium text-gray-900 mb-3"${_scopeId}>Analysis Summary</h4><div class="space-y-3"${_scopeId}><div${_scopeId}><div class="flex items-center justify-between text-sm mb-1"${_scopeId}><span${_scopeId}>Market Sentiment</span><span class="font-medium"${_scopeId}>${ssrInterpolate(Math.round(project.sentiment * 100))}%</span></div><div class="w-full bg-gray-200 rounded-full h-2"${_scopeId}><div class="bg-green-500 h-2 rounded-full transition-all duration-300" style="${ssrRenderStyle({ width: project.sentiment * 100 + "%" })}"${_scopeId}></div></div></div><div class="text-sm"${_scopeId}><span class="text-gray-600"${_scopeId}>Last Analysis:</span><span class="font-medium ml-1"${_scopeId}>${ssrInterpolate(project.lastAnalysis)}</span></div><div class="text-sm"${_scopeId}><span class="text-gray-600"${_scopeId}>Total Issues:</span><span class="font-medium ml-1"${_scopeId}>${ssrInterpolate(getTotalIssues(project))}</span></div></div></div></div><div class="flex items-center space-x-3"${_scopeId}><button class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium"${_scopeId}> View Details </button>`);
              if (project.status !== "analyzing") {
                _push2(`<button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium"${_scopeId}>${ssrInterpolate(project.status === "pending" ? "Start Analysis" : "Re-analyze")}</button>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium"${_scopeId}> Export </button></div></div></div>`);
            });
            _push2(`<!--]--></div>`);
            if (projects.value.length === 0) {
              _push2(`<div class="text-center py-12"${_scopeId}><div class="text-6xl mb-4"${_scopeId}>🏗️</div><h3 class="text-lg font-medium text-gray-900 mb-2"${_scopeId}>No projects yet</h3><p class="text-gray-600 mb-6"${_scopeId}>Get started by creating your first blockchain security analysis project.</p><button class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors"${_scopeId}> Create Your First Project </button></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6"${_scopeId}><div class="flex items-start space-x-3"${_scopeId}><div class="text-2xl"${_scopeId}>💡</div><div${_scopeId}><h3 class="text-lg font-semibold text-blue-900 mb-2"${_scopeId}>Demo Features</h3><ul class="text-sm text-blue-800 space-y-1"${_scopeId}><li${_scopeId}>• Real-time security analysis with AI-powered vulnerability detection</li><li${_scopeId}>• Market sentiment analysis from social media and news sources</li><li${_scopeId}>• Automated smart contract auditing with detailed reports</li><li${_scopeId}>• Multi-chain support for Ethereum, Polygon, and other networks</li></ul></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "flex items-center justify-between mb-8" }, [
                createVNode("div", null, [
                  createVNode("h1", { class: "text-3xl font-bold text-gray-900 mb-2" }, "Projects"),
                  createVNode("p", { class: "text-gray-600" }, "Manage and monitor your blockchain security analysis projects")
                ]),
                createVNode("button", {
                  onClick: ($event) => showNewProjectModal.value = true,
                  class: "px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors flex items-center space-x-2"
                }, [
                  (openBlock(), createBlock("svg", {
                    class: "w-5 h-5",
                    fill: "none",
                    stroke: "currentColor",
                    viewBox: "0 0 24 24"
                  }, [
                    createVNode("path", {
                      "stroke-linecap": "round",
                      "stroke-linejoin": "round",
                      "stroke-width": "2",
                      d: "M12 6v6m0 0v6m0-6h6m-6 0H6"
                    })
                  ])),
                  createVNode("span", null, "New Project")
                ], 8, ["onClick"])
              ]),
              createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-2 gap-6" }, [
                (openBlock(true), createBlock(Fragment, null, renderList(projects.value, (project) => {
                  return openBlock(), createBlock("div", {
                    key: project.id,
                    class: "bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                  }, [
                    createVNode("div", { class: "p-6 border-b border-gray-200" }, [
                      createVNode("div", { class: "flex items-start justify-between mb-4" }, [
                        createVNode("div", { class: "flex-1" }, [
                          createVNode("h3", { class: "text-lg font-semibold text-gray-900 mb-1" }, toDisplayString(project.name), 1),
                          createVNode("p", { class: "text-sm text-gray-600 mb-3" }, toDisplayString(project.description), 1),
                          createVNode("div", { class: "flex items-center space-x-4 text-xs text-gray-500" }, [
                            createVNode("span", null, "📍 " + toDisplayString(project.network), 1),
                            createVNode("span", null, "📄 " + toDisplayString(project.contractAddress.slice(0, 10)) + "...", 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex items-center space-x-2" }, [
                          createVNode("span", {
                            class: [getRiskColor(project.risk), "px-3 py-1 text-xs font-medium rounded-full border"]
                          }, toDisplayString(project.risk.toUpperCase()) + " RISK ", 3),
                          createVNode("span", {
                            class: [getStatusColor(project.status), "px-3 py-1 text-xs font-medium rounded-full border"]
                          }, toDisplayString(project.status.toUpperCase()), 3)
                        ])
                      ]),
                      project.status === "analyzing" ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mb-4"
                      }, [
                        createVNode("div", { class: "flex items-center justify-between text-sm text-gray-600 mb-1" }, [
                          createVNode("span", null, "Analysis Progress"),
                          createVNode("span", null, toDisplayString(Math.round(project.progress)) + "%", 1)
                        ]),
                        createVNode("div", { class: "w-full bg-gray-200 rounded-full h-2" }, [
                          createVNode("div", {
                            class: "bg-blue-500 h-2 rounded-full transition-all duration-300",
                            style: { width: project.progress + "%" }
                          }, null, 4)
                        ])
                      ])) : createCommentVNode("", true)
                    ]),
                    createVNode("div", { class: "p-6" }, [
                      createVNode("div", { class: "grid grid-cols-2 gap-6 mb-6" }, [
                        createVNode("div", null, [
                          createVNode("h4", { class: "text-sm font-medium text-gray-900 mb-3" }, "Security Issues"),
                          createVNode("div", { class: "space-y-2" }, [
                            createVNode("div", { class: "flex items-center justify-between text-sm" }, [
                              createVNode("div", { class: "flex items-center space-x-2" }, [
                                createVNode("div", { class: "w-3 h-3 bg-red-500 rounded-full" }),
                                createVNode("span", null, "Critical")
                              ]),
                              createVNode("span", { class: "font-medium" }, toDisplayString(project.criticalIssues), 1)
                            ]),
                            createVNode("div", { class: "flex items-center justify-between text-sm" }, [
                              createVNode("div", { class: "flex items-center space-x-2" }, [
                                createVNode("div", { class: "w-3 h-3 bg-orange-500 rounded-full" }),
                                createVNode("span", null, "High")
                              ]),
                              createVNode("span", { class: "font-medium" }, toDisplayString(project.highIssues), 1)
                            ]),
                            createVNode("div", { class: "flex items-center justify-between text-sm" }, [
                              createVNode("div", { class: "flex items-center space-x-2" }, [
                                createVNode("div", { class: "w-3 h-3 bg-yellow-500 rounded-full" }),
                                createVNode("span", null, "Medium")
                              ]),
                              createVNode("span", { class: "font-medium" }, toDisplayString(project.mediumIssues), 1)
                            ]),
                            createVNode("div", { class: "flex items-center justify-between text-sm" }, [
                              createVNode("div", { class: "flex items-center space-x-2" }, [
                                createVNode("div", { class: "w-3 h-3 bg-blue-500 rounded-full" }),
                                createVNode("span", null, "Low")
                              ]),
                              createVNode("span", { class: "font-medium" }, toDisplayString(project.lowIssues), 1)
                            ])
                          ])
                        ]),
                        createVNode("div", null, [
                          createVNode("h4", { class: "text-sm font-medium text-gray-900 mb-3" }, "Analysis Summary"),
                          createVNode("div", { class: "space-y-3" }, [
                            createVNode("div", null, [
                              createVNode("div", { class: "flex items-center justify-between text-sm mb-1" }, [
                                createVNode("span", null, "Market Sentiment"),
                                createVNode("span", { class: "font-medium" }, toDisplayString(Math.round(project.sentiment * 100)) + "%", 1)
                              ]),
                              createVNode("div", { class: "w-full bg-gray-200 rounded-full h-2" }, [
                                createVNode("div", {
                                  class: "bg-green-500 h-2 rounded-full transition-all duration-300",
                                  style: { width: project.sentiment * 100 + "%" }
                                }, null, 4)
                              ])
                            ]),
                            createVNode("div", { class: "text-sm" }, [
                              createVNode("span", { class: "text-gray-600" }, "Last Analysis:"),
                              createVNode("span", { class: "font-medium ml-1" }, toDisplayString(project.lastAnalysis), 1)
                            ]),
                            createVNode("div", { class: "text-sm" }, [
                              createVNode("span", { class: "text-gray-600" }, "Total Issues:"),
                              createVNode("span", { class: "font-medium ml-1" }, toDisplayString(getTotalIssues(project)), 1)
                            ])
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "flex items-center space-x-3" }, [
                        createVNode("button", { class: "flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium" }, " View Details "),
                        project.status !== "analyzing" ? (openBlock(), createBlock("button", {
                          key: 0,
                          onClick: ($event) => startAnalysis(project),
                          class: "px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium"
                        }, toDisplayString(project.status === "pending" ? "Start Analysis" : "Re-analyze"), 9, ["onClick"])) : createCommentVNode("", true),
                        createVNode("button", { class: "px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium" }, " Export ")
                      ])
                    ])
                  ]);
                }), 128))
              ]),
              projects.value.length === 0 ? (openBlock(), createBlock("div", {
                key: 0,
                class: "text-center py-12"
              }, [
                createVNode("div", { class: "text-6xl mb-4" }, "🏗️"),
                createVNode("h3", { class: "text-lg font-medium text-gray-900 mb-2" }, "No projects yet"),
                createVNode("p", { class: "text-gray-600 mb-6" }, "Get started by creating your first blockchain security analysis project."),
                createVNode("button", {
                  onClick: ($event) => showNewProjectModal.value = true,
                  class: "px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors"
                }, " Create Your First Project ", 8, ["onClick"])
              ])) : createCommentVNode("", true),
              createVNode("div", { class: "mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6" }, [
                createVNode("div", { class: "flex items-start space-x-3" }, [
                  createVNode("div", { class: "text-2xl" }, "💡"),
                  createVNode("div", null, [
                    createVNode("h3", { class: "text-lg font-semibold text-blue-900 mb-2" }, "Demo Features"),
                    createVNode("ul", { class: "text-sm text-blue-800 space-y-1" }, [
                      createVNode("li", null, "• Real-time security analysis with AI-powered vulnerability detection"),
                      createVNode("li", null, "• Market sentiment analysis from social media and news sources"),
                      createVNode("li", null, "• Automated smart contract auditing with detailed reports"),
                      createVNode("li", null, "• Multi-chain support for Ethereum, Polygon, and other networks")
                    ])
                  ])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Projects.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
