import { ref, computed, onMounted, withCtx, unref, createTextVNode, createVNode, createBlock, createCommentVNode, toDisplayString, openBlock, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderClass, ssrRenderStyle, ssrIncludeBooleanAttr, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { Head, Link, router } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./AuthenticatedLayout-8TbwyeTu.js";
import { CreditCardIcon, ChartBarIcon, CloudIcon } from "@heroicons/vue/24/outline";
import "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const _sfc_main = {
  __name: "Dashboard",
  __ssrInlineRender: true,
  props: {
    plans: Object,
    currentSubscription: Object,
    currentUsage: Object,
    usagePercentages: Object,
    paymentMethods: Array,
    defaultPaymentMethod: Object
  },
  setup(__props) {
    const props = __props;
    const processing = ref(false);
    const invoices = ref([]);
    const planLimits = computed(() => {
      if (!props.currentSubscription) {
        return {
          analysis_limit: 3,
          api_calls_limit: 100,
          tokens_limit: 1e4
        };
      }
      const planName = props.currentSubscription.name;
      const plan = props.plans[planName];
      return (plan == null ? void 0 : plan.features) || {};
    });
    const formatNumber = (num) => {
      if (num >= 1e6) {
        return (num / 1e6).toFixed(1) + "M";
      }
      if (num >= 1e3) {
        return (num / 1e3).toFixed(1) + "K";
      }
      return (num == null ? void 0 : num.toLocaleString()) || "0";
    };
    const formatDate = (date) => {
      return new Date(date).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric"
      });
    };
    const formatStatus = (status) => {
      return status.replace("_", " ").replace(/\b\w/g, (l) => l.toUpperCase());
    };
    const getStatusClass = (status) => {
      const classes = {
        "active": "text-green-800 bg-green-100 px-2 py-1 rounded-full text-xs",
        "trialing": "text-blue-800 bg-blue-100 px-2 py-1 rounded-full text-xs",
        "canceled": "text-red-800 bg-red-100 px-2 py-1 rounded-full text-xs",
        "past_due": "text-yellow-800 bg-yellow-100 px-2 py-1 rounded-full text-xs"
      };
      return classes[status] || "text-gray-800 bg-gray-100 px-2 py-1 rounded-full text-xs";
    };
    const getInvoiceStatusClass = (status) => {
      const classes = {
        "paid": "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800",
        "open": "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800",
        "void": "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800",
        "uncollectible": "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800"
      };
      return classes[status] || "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800";
    };
    const getLimit = (type) => {
      const key = type + "_limit";
      return formatNumber(planLimits.value[key]) || "Unlimited";
    };
    const cancelSubscription = async () => {
      if (!confirm("Are you sure you want to cancel your subscription? You will continue to have access until the end of your billing period.")) {
        return;
      }
      processing.value = true;
      try {
        await router.delete(route("billing.subscription.cancel"));
      } catch (error) {
        console.error("Error cancelling subscription:", error);
      } finally {
        processing.value = false;
      }
    };
    const resumeSubscription = async () => {
      processing.value = true;
      try {
        await router.post(route("billing.subscription.resume"));
      } catch (error) {
        console.error("Error resuming subscription:", error);
      } finally {
        processing.value = false;
      }
    };
    const loadBillingHistory = async () => {
      try {
        const response = await fetch(route("billing.history"));
        const data = await response.json();
        invoices.value = data.invoices || [];
      } catch (error) {
        console.error("Error loading billing history:", error);
      }
    };
    onMounted(() => {
      loadBillingHistory();
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a, _b, _c, _d, _e, _f;
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Billing Dashboard" }, null, _parent2, _scopeId));
            _push2(`<div class="min-h-screen bg-gray-50 py-8"${_scopeId}><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"${_scopeId}><div class="md:flex md:items-center md:justify-between mb-8"${_scopeId}><div class="flex-1 min-w-0"${_scopeId}><h2 class="text-3xl font-bold leading-7 text-gray-900 sm:text-4xl sm:truncate"${_scopeId}> Billing Dashboard </h2><p class="mt-1 text-sm text-gray-500"${_scopeId}> Manage your subscription and view usage statistics </p></div><div class="mt-4 flex md:mt-0 md:ml-4"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: _ctx.route("billing.plans"),
              class: "ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` View Plans `);
                } else {
                  return [
                    createTextVNode(" View Plans ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8"${_scopeId}><div class="bg-white overflow-hidden shadow rounded-lg"${_scopeId}><div class="p-5"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(CreditCardIcon), { class: "h-6 w-6 text-gray-400" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-5 w-0 flex-1"${_scopeId}><dl${_scopeId}><dt class="text-sm font-medium text-gray-500 truncate"${_scopeId}> Current Plan </dt><dd class="text-lg font-medium text-gray-900"${_scopeId}>${ssrInterpolate(__props.currentSubscription ? __props.currentSubscription.name.charAt(0).toUpperCase() + __props.currentSubscription.name.slice(1) : "Free Tier")}</dd></dl></div></div></div><div class="bg-gray-50 px-5 py-3"${_scopeId}><div class="text-sm"${_scopeId}>`);
            if (__props.currentSubscription) {
              _push2(`<span class="font-medium text-gray-900"${_scopeId}> Status: <span class="${ssrRenderClass(getStatusClass(__props.currentSubscription.status))}"${_scopeId}>${ssrInterpolate(formatStatus(__props.currentSubscription.status))}</span></span>`);
            } else {
              _push2(`<span class="text-gray-500"${_scopeId}> Upgrade to unlock more features </span>`);
            }
            _push2(`</div></div></div><div class="bg-white overflow-hidden shadow rounded-lg"${_scopeId}><div class="p-5"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(ChartBarIcon), { class: "h-6 w-6 text-gray-400" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-5 w-0 flex-1"${_scopeId}><dl${_scopeId}><dt class="text-sm font-medium text-gray-500 truncate"${_scopeId}> Analyses This Month </dt><dd class="text-lg font-medium text-gray-900"${_scopeId}>${ssrInterpolate(__props.currentUsage ? __props.currentUsage.analysis : 0)}</dd></dl></div></div></div><div class="bg-gray-50 px-5 py-3"${_scopeId}><div class="text-sm"${_scopeId}>`);
            if (__props.usagePercentages && __props.usagePercentages.analysis !== void 0) {
              _push2(`<span class="text-gray-600"${_scopeId}>${ssrInterpolate(Math.round(__props.usagePercentages.analysis))}% of limit used </span>`);
            } else {
              _push2(`<span class="text-gray-500"${_scopeId}> No limit tracking </span>`);
            }
            _push2(`</div></div></div><div class="bg-white overflow-hidden shadow rounded-lg"${_scopeId}><div class="p-5"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(CloudIcon), { class: "h-6 w-6 text-gray-400" }, null, _parent2, _scopeId));
            _push2(`</div><div class="ml-5 w-0 flex-1"${_scopeId}><dl${_scopeId}><dt class="text-sm font-medium text-gray-500 truncate"${_scopeId}> API Calls This Month </dt><dd class="text-lg font-medium text-gray-900"${_scopeId}>${ssrInterpolate(formatNumber(__props.currentUsage ? __props.currentUsage.api_calls : 0))}</dd></dl></div></div></div><div class="bg-gray-50 px-5 py-3"${_scopeId}><div class="text-sm"${_scopeId}>`);
            if (__props.usagePercentages && __props.usagePercentages.api_calls !== void 0) {
              _push2(`<span class="text-gray-600"${_scopeId}>${ssrInterpolate(Math.round(__props.usagePercentages.api_calls))}% of limit used </span>`);
            } else {
              _push2(`<span class="text-gray-500"${_scopeId}> No limit tracking </span>`);
            }
            _push2(`</div></div></div></div>`);
            if (__props.currentUsage) {
              _push2(`<div class="bg-white shadow rounded-lg mb-8"${_scopeId}><div class="px-4 py-5 sm:p-6"${_scopeId}><h3 class="text-lg leading-6 font-medium text-gray-900 mb-6"${_scopeId}> Usage Overview </h3><div class="grid grid-cols-1 gap-6 sm:grid-cols-3"${_scopeId}><div${_scopeId}><div class="flex items-center justify-between mb-2"${_scopeId}><span class="text-sm font-medium text-gray-700"${_scopeId}>Analyses</span><span class="text-sm text-gray-500"${_scopeId}>${ssrInterpolate(__props.currentUsage.analysis)} / ${ssrInterpolate(getLimit("analysis"))}</span></div><div class="w-full bg-gray-200 rounded-full h-2"${_scopeId}><div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="${ssrRenderStyle({ width: Math.min(100, ((_a = __props.usagePercentages) == null ? void 0 : _a.analysis) || 0) + "%" })}"${_scopeId}></div></div></div><div${_scopeId}><div class="flex items-center justify-between mb-2"${_scopeId}><span class="text-sm font-medium text-gray-700"${_scopeId}>API Calls</span><span class="text-sm text-gray-500"${_scopeId}>${ssrInterpolate(formatNumber(__props.currentUsage.api_calls))} / ${ssrInterpolate(formatNumber(getLimit("api_calls")))}</span></div><div class="w-full bg-gray-200 rounded-full h-2"${_scopeId}><div class="bg-green-600 h-2 rounded-full transition-all duration-300" style="${ssrRenderStyle({ width: Math.min(100, ((_b = __props.usagePercentages) == null ? void 0 : _b.api_calls) || 0) + "%" })}"${_scopeId}></div></div></div><div${_scopeId}><div class="flex items-center justify-between mb-2"${_scopeId}><span class="text-sm font-medium text-gray-700"${_scopeId}>AI Tokens</span><span class="text-sm text-gray-500"${_scopeId}>${ssrInterpolate(formatNumber(__props.currentUsage.tokens))} / ${ssrInterpolate(formatNumber(getLimit("tokens")))}</span></div><div class="w-full bg-gray-200 rounded-full h-2"${_scopeId}><div class="bg-yellow-600 h-2 rounded-full transition-all duration-300" style="${ssrRenderStyle({ width: Math.min(100, ((_c = __props.usagePercentages) == null ? void 0 : _c.tokens) || 0) + "%" })}"${_scopeId}></div></div></div></div>`);
              if (__props.currentUsage) {
                _push2(`<div class="mt-4 text-sm text-gray-500"${_scopeId}> Billing period: ${ssrInterpolate(__props.currentUsage.period_start)} - ${ssrInterpolate(__props.currentUsage.period_end)}</div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div></div>`);
            } else {
              _push2(`<!---->`);
            }
            if (__props.currentSubscription) {
              _push2(`<div class="bg-white shadow rounded-lg mb-8"${_scopeId}><div class="px-4 py-5 sm:p-6"${_scopeId}><h3 class="text-lg leading-6 font-medium text-gray-900 mb-4"${_scopeId}> Subscription Management </h3><div class="grid grid-cols-1 gap-4 sm:grid-cols-2"${_scopeId}><div${_scopeId}><dt class="text-sm font-medium text-gray-500"${_scopeId}>Plan</dt><dd class="mt-1 text-sm text-gray-900"${_scopeId}>${ssrInterpolate(__props.currentSubscription.name.charAt(0).toUpperCase() + __props.currentSubscription.name.slice(1))}</dd></div><div${_scopeId}><dt class="text-sm font-medium text-gray-500"${_scopeId}>Status</dt><dd class="mt-1 text-sm text-gray-900"${_scopeId}><span class="${ssrRenderClass(getStatusClass(__props.currentSubscription.status))}"${_scopeId}>${ssrInterpolate(formatStatus(__props.currentSubscription.status))}</span></dd></div>`);
              if (__props.currentSubscription.trial_ends_at) {
                _push2(`<div${_scopeId}><dt class="text-sm font-medium text-gray-500"${_scopeId}>Trial Ends</dt><dd class="mt-1 text-sm text-gray-900"${_scopeId}>${ssrInterpolate(formatDate(__props.currentSubscription.trial_ends_at))}</dd></div>`);
              } else {
                _push2(`<!---->`);
              }
              if (__props.currentSubscription.ends_at) {
                _push2(`<div${_scopeId}><dt class="text-sm font-medium text-gray-500"${_scopeId}>${ssrInterpolate(__props.currentSubscription.cancelled ? "Ends" : "Next Billing")}</dt><dd class="mt-1 text-sm text-gray-900"${_scopeId}>${ssrInterpolate(formatDate(__props.currentSubscription.ends_at))}</dd></div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div><div class="mt-6 flex flex-wrap gap-3"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(Link), {
                href: _ctx.route("billing.plans"),
                class: "inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(` Change Plan `);
                  } else {
                    return [
                      createTextVNode(" Change Plan ")
                    ];
                  }
                }),
                _: 1
              }, _parent2, _scopeId));
              if (__props.currentSubscription.cancelled && __props.currentSubscription.on_grace_period) {
                _push2(`<button${ssrIncludeBooleanAttr(processing.value) ? " disabled" : ""} class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"${_scopeId}> Resume Subscription </button>`);
              } else if (!__props.currentSubscription.cancelled) {
                _push2(`<button${ssrIncludeBooleanAttr(processing.value) ? " disabled" : ""} class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"${_scopeId}> Cancel Subscription </button>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="bg-white shadow rounded-lg"${_scopeId}><div class="px-4 py-5 sm:p-6"${_scopeId}><div class="flex items-center justify-between mb-6"${_scopeId}><h3 class="text-lg leading-6 font-medium text-gray-900"${_scopeId}> Recent Invoices </h3>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: _ctx.route("billing.history"),
              class: "text-sm text-indigo-600 hover:text-indigo-500"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` View all `);
                } else {
                  return [
                    createTextVNode(" View all ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div>`);
            if (invoices.value && invoices.value.length > 0) {
              _push2(`<div class="overflow-hidden"${_scopeId}><table class="min-w-full divide-y divide-gray-200"${_scopeId}><thead class="bg-gray-50"${_scopeId}><tr${_scopeId}><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Date </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Amount </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Status </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Actions </th></tr></thead><tbody class="bg-white divide-y divide-gray-200"${_scopeId}><!--[-->`);
              ssrRenderList(invoices.value.slice(0, 5), (invoice) => {
                _push2(`<tr${_scopeId}><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"${_scopeId}>${ssrInterpolate(formatDate(invoice.date))}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"${_scopeId}> $${ssrInterpolate((invoice.total / 100).toFixed(2))}</td><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><span class="${ssrRenderClass(getInvoiceStatusClass(invoice.status))}"${_scopeId}>${ssrInterpolate(invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1))}</span></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium"${_scopeId}><a${ssrRenderAttr("href", _ctx.route("billing.invoices.download", invoice.id))} class="text-indigo-600 hover:text-indigo-900" target="_blank"${_scopeId}> Download </a></td></tr>`);
              });
              _push2(`<!--]--></tbody></table></div>`);
            } else {
              _push2(`<div class="text-center py-6"${_scopeId}><p class="text-sm text-gray-500"${_scopeId}>No invoices available</p></div>`);
            }
            _push2(`</div></div></div></div>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Billing Dashboard" }),
              createVNode("div", { class: "min-h-screen bg-gray-50 py-8" }, [
                createVNode("div", { class: "max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "md:flex md:items-center md:justify-between mb-8" }, [
                    createVNode("div", { class: "flex-1 min-w-0" }, [
                      createVNode("h2", { class: "text-3xl font-bold leading-7 text-gray-900 sm:text-4xl sm:truncate" }, " Billing Dashboard "),
                      createVNode("p", { class: "mt-1 text-sm text-gray-500" }, " Manage your subscription and view usage statistics ")
                    ]),
                    createVNode("div", { class: "mt-4 flex md:mt-0 md:ml-4" }, [
                      createVNode(unref(Link), {
                        href: _ctx.route("billing.plans"),
                        class: "ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                      }, {
                        default: withCtx(() => [
                          createTextVNode(" View Plans ")
                        ]),
                        _: 1
                      }, 8, ["href"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8" }, [
                    createVNode("div", { class: "bg-white overflow-hidden shadow rounded-lg" }, [
                      createVNode("div", { class: "p-5" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(CreditCardIcon), { class: "h-6 w-6 text-gray-400" })
                          ]),
                          createVNode("div", { class: "ml-5 w-0 flex-1" }, [
                            createVNode("dl", null, [
                              createVNode("dt", { class: "text-sm font-medium text-gray-500 truncate" }, " Current Plan "),
                              createVNode("dd", { class: "text-lg font-medium text-gray-900" }, toDisplayString(__props.currentSubscription ? __props.currentSubscription.name.charAt(0).toUpperCase() + __props.currentSubscription.name.slice(1) : "Free Tier"), 1)
                            ])
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "bg-gray-50 px-5 py-3" }, [
                        createVNode("div", { class: "text-sm" }, [
                          __props.currentSubscription ? (openBlock(), createBlock("span", {
                            key: 0,
                            class: "font-medium text-gray-900"
                          }, [
                            createTextVNode(" Status: "),
                            createVNode("span", {
                              class: getStatusClass(__props.currentSubscription.status)
                            }, toDisplayString(formatStatus(__props.currentSubscription.status)), 3)
                          ])) : (openBlock(), createBlock("span", {
                            key: 1,
                            class: "text-gray-500"
                          }, " Upgrade to unlock more features "))
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "bg-white overflow-hidden shadow rounded-lg" }, [
                      createVNode("div", { class: "p-5" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(ChartBarIcon), { class: "h-6 w-6 text-gray-400" })
                          ]),
                          createVNode("div", { class: "ml-5 w-0 flex-1" }, [
                            createVNode("dl", null, [
                              createVNode("dt", { class: "text-sm font-medium text-gray-500 truncate" }, " Analyses This Month "),
                              createVNode("dd", { class: "text-lg font-medium text-gray-900" }, toDisplayString(__props.currentUsage ? __props.currentUsage.analysis : 0), 1)
                            ])
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "bg-gray-50 px-5 py-3" }, [
                        createVNode("div", { class: "text-sm" }, [
                          __props.usagePercentages && __props.usagePercentages.analysis !== void 0 ? (openBlock(), createBlock("span", {
                            key: 0,
                            class: "text-gray-600"
                          }, toDisplayString(Math.round(__props.usagePercentages.analysis)) + "% of limit used ", 1)) : (openBlock(), createBlock("span", {
                            key: 1,
                            class: "text-gray-500"
                          }, " No limit tracking "))
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "bg-white overflow-hidden shadow rounded-lg" }, [
                      createVNode("div", { class: "p-5" }, [
                        createVNode("div", { class: "flex items-center" }, [
                          createVNode("div", { class: "flex-shrink-0" }, [
                            createVNode(unref(CloudIcon), { class: "h-6 w-6 text-gray-400" })
                          ]),
                          createVNode("div", { class: "ml-5 w-0 flex-1" }, [
                            createVNode("dl", null, [
                              createVNode("dt", { class: "text-sm font-medium text-gray-500 truncate" }, " API Calls This Month "),
                              createVNode("dd", { class: "text-lg font-medium text-gray-900" }, toDisplayString(formatNumber(__props.currentUsage ? __props.currentUsage.api_calls : 0)), 1)
                            ])
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "bg-gray-50 px-5 py-3" }, [
                        createVNode("div", { class: "text-sm" }, [
                          __props.usagePercentages && __props.usagePercentages.api_calls !== void 0 ? (openBlock(), createBlock("span", {
                            key: 0,
                            class: "text-gray-600"
                          }, toDisplayString(Math.round(__props.usagePercentages.api_calls)) + "% of limit used ", 1)) : (openBlock(), createBlock("span", {
                            key: 1,
                            class: "text-gray-500"
                          }, " No limit tracking "))
                        ])
                      ])
                    ])
                  ]),
                  __props.currentUsage ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "bg-white shadow rounded-lg mb-8"
                  }, [
                    createVNode("div", { class: "px-4 py-5 sm:p-6" }, [
                      createVNode("h3", { class: "text-lg leading-6 font-medium text-gray-900 mb-6" }, " Usage Overview "),
                      createVNode("div", { class: "grid grid-cols-1 gap-6 sm:grid-cols-3" }, [
                        createVNode("div", null, [
                          createVNode("div", { class: "flex items-center justify-between mb-2" }, [
                            createVNode("span", { class: "text-sm font-medium text-gray-700" }, "Analyses"),
                            createVNode("span", { class: "text-sm text-gray-500" }, toDisplayString(__props.currentUsage.analysis) + " / " + toDisplayString(getLimit("analysis")), 1)
                          ]),
                          createVNode("div", { class: "w-full bg-gray-200 rounded-full h-2" }, [
                            createVNode("div", {
                              class: "bg-indigo-600 h-2 rounded-full transition-all duration-300",
                              style: { width: Math.min(100, ((_d = __props.usagePercentages) == null ? void 0 : _d.analysis) || 0) + "%" }
                            }, null, 4)
                          ])
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "flex items-center justify-between mb-2" }, [
                            createVNode("span", { class: "text-sm font-medium text-gray-700" }, "API Calls"),
                            createVNode("span", { class: "text-sm text-gray-500" }, toDisplayString(formatNumber(__props.currentUsage.api_calls)) + " / " + toDisplayString(formatNumber(getLimit("api_calls"))), 1)
                          ]),
                          createVNode("div", { class: "w-full bg-gray-200 rounded-full h-2" }, [
                            createVNode("div", {
                              class: "bg-green-600 h-2 rounded-full transition-all duration-300",
                              style: { width: Math.min(100, ((_e = __props.usagePercentages) == null ? void 0 : _e.api_calls) || 0) + "%" }
                            }, null, 4)
                          ])
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "flex items-center justify-between mb-2" }, [
                            createVNode("span", { class: "text-sm font-medium text-gray-700" }, "AI Tokens"),
                            createVNode("span", { class: "text-sm text-gray-500" }, toDisplayString(formatNumber(__props.currentUsage.tokens)) + " / " + toDisplayString(formatNumber(getLimit("tokens"))), 1)
                          ]),
                          createVNode("div", { class: "w-full bg-gray-200 rounded-full h-2" }, [
                            createVNode("div", {
                              class: "bg-yellow-600 h-2 rounded-full transition-all duration-300",
                              style: { width: Math.min(100, ((_f = __props.usagePercentages) == null ? void 0 : _f.tokens) || 0) + "%" }
                            }, null, 4)
                          ])
                        ])
                      ]),
                      __props.currentUsage ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mt-4 text-sm text-gray-500"
                      }, " Billing period: " + toDisplayString(__props.currentUsage.period_start) + " - " + toDisplayString(__props.currentUsage.period_end), 1)) : createCommentVNode("", true)
                    ])
                  ])) : createCommentVNode("", true),
                  __props.currentSubscription ? (openBlock(), createBlock("div", {
                    key: 1,
                    class: "bg-white shadow rounded-lg mb-8"
                  }, [
                    createVNode("div", { class: "px-4 py-5 sm:p-6" }, [
                      createVNode("h3", { class: "text-lg leading-6 font-medium text-gray-900 mb-4" }, " Subscription Management "),
                      createVNode("div", { class: "grid grid-cols-1 gap-4 sm:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode("dt", { class: "text-sm font-medium text-gray-500" }, "Plan"),
                          createVNode("dd", { class: "mt-1 text-sm text-gray-900" }, toDisplayString(__props.currentSubscription.name.charAt(0).toUpperCase() + __props.currentSubscription.name.slice(1)), 1)
                        ]),
                        createVNode("div", null, [
                          createVNode("dt", { class: "text-sm font-medium text-gray-500" }, "Status"),
                          createVNode("dd", { class: "mt-1 text-sm text-gray-900" }, [
                            createVNode("span", {
                              class: getStatusClass(__props.currentSubscription.status)
                            }, toDisplayString(formatStatus(__props.currentSubscription.status)), 3)
                          ])
                        ]),
                        __props.currentSubscription.trial_ends_at ? (openBlock(), createBlock("div", { key: 0 }, [
                          createVNode("dt", { class: "text-sm font-medium text-gray-500" }, "Trial Ends"),
                          createVNode("dd", { class: "mt-1 text-sm text-gray-900" }, toDisplayString(formatDate(__props.currentSubscription.trial_ends_at)), 1)
                        ])) : createCommentVNode("", true),
                        __props.currentSubscription.ends_at ? (openBlock(), createBlock("div", { key: 1 }, [
                          createVNode("dt", { class: "text-sm font-medium text-gray-500" }, toDisplayString(__props.currentSubscription.cancelled ? "Ends" : "Next Billing"), 1),
                          createVNode("dd", { class: "mt-1 text-sm text-gray-900" }, toDisplayString(formatDate(__props.currentSubscription.ends_at)), 1)
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "mt-6 flex flex-wrap gap-3" }, [
                        createVNode(unref(Link), {
                          href: _ctx.route("billing.plans"),
                          class: "inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        }, {
                          default: withCtx(() => [
                            createTextVNode(" Change Plan ")
                          ]),
                          _: 1
                        }, 8, ["href"]),
                        __props.currentSubscription.cancelled && __props.currentSubscription.on_grace_period ? (openBlock(), createBlock("button", {
                          key: 0,
                          onClick: resumeSubscription,
                          disabled: processing.value,
                          class: "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                        }, " Resume Subscription ", 8, ["disabled"])) : !__props.currentSubscription.cancelled ? (openBlock(), createBlock("button", {
                          key: 1,
                          onClick: cancelSubscription,
                          disabled: processing.value,
                          class: "inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                        }, " Cancel Subscription ", 8, ["disabled"])) : createCommentVNode("", true)
                      ])
                    ])
                  ])) : createCommentVNode("", true),
                  createVNode("div", { class: "bg-white shadow rounded-lg" }, [
                    createVNode("div", { class: "px-4 py-5 sm:p-6" }, [
                      createVNode("div", { class: "flex items-center justify-between mb-6" }, [
                        createVNode("h3", { class: "text-lg leading-6 font-medium text-gray-900" }, " Recent Invoices "),
                        createVNode(unref(Link), {
                          href: _ctx.route("billing.history"),
                          class: "text-sm text-indigo-600 hover:text-indigo-500"
                        }, {
                          default: withCtx(() => [
                            createTextVNode(" View all ")
                          ]),
                          _: 1
                        }, 8, ["href"])
                      ]),
                      invoices.value && invoices.value.length > 0 ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "overflow-hidden"
                      }, [
                        createVNode("table", { class: "min-w-full divide-y divide-gray-200" }, [
                          createVNode("thead", { class: "bg-gray-50" }, [
                            createVNode("tr", null, [
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Date "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Amount "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Status "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Actions ")
                            ])
                          ]),
                          createVNode("tbody", { class: "bg-white divide-y divide-gray-200" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(invoices.value.slice(0, 5), (invoice) => {
                              return openBlock(), createBlock("tr", {
                                key: invoice.id
                              }, [
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900" }, toDisplayString(formatDate(invoice.date)), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900" }, " $" + toDisplayString((invoice.total / 100).toFixed(2)), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                  createVNode("span", {
                                    class: getInvoiceStatusClass(invoice.status)
                                  }, toDisplayString(invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)), 3)
                                ]),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm font-medium" }, [
                                  createVNode("a", {
                                    href: _ctx.route("billing.invoices.download", invoice.id),
                                    class: "text-indigo-600 hover:text-indigo-900",
                                    target: "_blank"
                                  }, " Download ", 8, ["href"])
                                ])
                              ]);
                            }), 128))
                          ])
                        ])
                      ])) : (openBlock(), createBlock("div", {
                        key: 1,
                        class: "text-center py-6"
                      }, [
                        createVNode("p", { class: "text-sm text-gray-500" }, "No invoices available")
                      ]))
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Billing/Dashboard.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
