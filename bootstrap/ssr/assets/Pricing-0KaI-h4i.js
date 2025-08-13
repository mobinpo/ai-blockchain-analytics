import { ref, computed, unref, withCtx, createVNode, createTextVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderClass, ssrRenderList, ssrInterpolate, ssrRenderVNode } from "vue/server-renderer";
import { Head, Link } from "@inertiajs/vue3";
import { A as ApplicationLogo } from "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const FeatureBool = {
  props: ["value"],
  template: `
    <svg v-if="value" class="h-5 w-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>
    <svg v-else class="h-5 w-5 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
    </svg>
  `
};
const FeatureNumber = {
  props: ["value"],
  template: `<span class="text-gray-900 font-medium">{{ value.toLocaleString() }}</span>`
};
const FeatureText = {
  props: ["value"],
  template: `<span class="text-gray-900 font-medium">{{ value }}</span>`
};
const __default__ = {
  components: {
    FeatureBool,
    FeatureNumber,
    FeatureText
  }
};
const _sfc_main = /* @__PURE__ */ Object.assign(__default__, {
  __name: "Pricing",
  __ssrInlineRender: true,
  props: {
    monthlyPlans: Array,
    yearlyPlans: Array,
    canLogin: Boolean,
    canRegister: Boolean
  },
  setup(__props) {
    const props = __props;
    const billingInterval = ref("monthly");
    const currentPlans = computed(() => {
      return billingInterval.value === "yearly" ? props.yearlyPlans : props.monthlyPlans;
    });
    const comparisonFeatures = [
      {
        name: "Blockchain Analysis",
        starter: 10,
        professional: 100,
        enterprise: "Unlimited"
      },
      {
        name: "Smart Contract Scanning",
        starter: true,
        professional: true,
        enterprise: true
      },
      {
        name: "AI-Powered Insights",
        starter: false,
        professional: true,
        enterprise: true
      },
      {
        name: "Real-time Monitoring",
        starter: false,
        professional: true,
        enterprise: true
      },
      {
        name: "Custom Reports",
        starter: false,
        professional: true,
        enterprise: true
      },
      {
        name: "Priority Support",
        starter: false,
        professional: false,
        enterprise: true
      },
      {
        name: "White-label Reports",
        starter: false,
        professional: false,
        enterprise: true
      },
      {
        name: "Dedicated Account Manager",
        starter: false,
        professional: false,
        enterprise: true
      }
    ];
    const faqs = [
      {
        question: "Can I change my plan at any time?",
        answer: "Yes, you can upgrade or downgrade your plan at any time. Changes will be prorated and reflected in your next billing cycle."
      },
      {
        question: "Do you offer a free trial?",
        answer: "Yes, all plans come with a free trial period. Starter and Professional plans include a 14-day trial, while Enterprise includes a 30-day trial."
      },
      {
        question: "What payment methods do you accept?",
        answer: "We accept all major credit cards (Visa, MasterCard, American Express) and support secure payments through Stripe."
      },
      {
        question: "Can I cancel my subscription anytime?",
        answer: "Yes, you can cancel your subscription at any time. Your access will continue until the end of your current billing period."
      },
      {
        question: "Do you offer discounts for annual billing?",
        answer: "Yes, annual billing offers a 20% discount compared to monthly billing across all plans."
      },
      {
        question: "Is there a setup fee?",
        answer: "No, there are no setup fees or hidden charges. You only pay the plan price listed above."
      }
    ];
    const getFeatureComponent = (value) => {
      if (typeof value === "boolean") {
        return "FeatureBool";
      } else if (typeof value === "number") {
        return "FeatureNumber";
      } else {
        return "FeatureText";
      }
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Pricing" }, null, _parent));
      _push(`<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100"><nav class="bg-white shadow-sm border-b border-gray-200"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex justify-between h-16"><div class="flex items-center">`);
      _push(ssrRenderComponent(unref(Link), {
        href: "/",
        class: "flex items-center"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(ApplicationLogo, { class: "block h-9 w-auto" }, null, _parent2, _scopeId));
            _push2(`<span class="ml-2 text-xl font-semibold text-gray-900"${_scopeId}>AI Blockchain Analytics</span>`);
          } else {
            return [
              createVNode(ApplicationLogo, { class: "block h-9 w-auto" }),
              createVNode("span", { class: "ml-2 text-xl font-semibold text-gray-900" }, "AI Blockchain Analytics")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div><div class="flex items-center space-x-4">`);
      if (__props.canLogin) {
        _push(`<div>`);
        if (_ctx.$page.props.auth.user) {
          _push(ssrRenderComponent(unref(Link), {
            href: _ctx.route("dashboard"),
            class: "text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(` Dashboard `);
              } else {
                return [
                  createTextVNode(" Dashboard ")
                ];
              }
            }),
            _: 1
          }, _parent));
        } else {
          _push(`<!--[-->`);
          _push(ssrRenderComponent(unref(Link), {
            href: _ctx.route("login"),
            class: "text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(` Log in `);
              } else {
                return [
                  createTextVNode(" Log in ")
                ];
              }
            }),
            _: 1
          }, _parent));
          if (__props.canRegister) {
            _push(ssrRenderComponent(unref(Link), {
              href: _ctx.route("register"),
              class: "bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
            }, {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(` Sign up `);
                } else {
                  return [
                    createTextVNode(" Sign up ")
                  ];
                }
              }),
              _: 1
            }, _parent));
          } else {
            _push(`<!---->`);
          }
          _push(`<!--]-->`);
        }
        _push(`</div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div></div></nav><div class="py-16"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center"><h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl lg:text-6xl"> Simple, transparent pricing </h1><p class="mt-6 text-xl text-gray-600 max-w-3xl mx-auto"> Choose the perfect plan for your blockchain analytics needs. Start your free trial today. </p></div></div><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex justify-center mb-12"><div class="bg-gray-100 p-1 rounded-lg"><button class="${ssrRenderClass([
        "px-6 py-2 text-sm font-medium rounded-md transition-colors",
        billingInterval.value === "monthly" ? "bg-white text-gray-900 shadow-sm" : "text-gray-600 hover:text-gray-900"
      ])}"> Monthly </button><button class="${ssrRenderClass([
        "px-6 py-2 text-sm font-medium rounded-md transition-colors relative",
        billingInterval.value === "yearly" ? "bg-white text-gray-900 shadow-sm" : "text-gray-600 hover:text-gray-900"
      ])}"> Yearly <span class="absolute -top-2 -right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full"> Save 20% </span></button></div></div><div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16"><!--[-->`);
      ssrRenderList(currentPlans.value, (plan) => {
        _push(`<div class="${ssrRenderClass([
          "bg-white rounded-2xl shadow-sm border-2 p-8 relative",
          plan.plan_tier === "professional" ? "border-indigo-200 ring-1 ring-indigo-200" : "border-gray-200"
        ])}">`);
        if (plan.plan_tier === "professional") {
          _push(`<div class="absolute -top-4 left-1/2 transform -translate-x-1/2"><span class="bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-medium"> Most Popular </span></div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<div class="text-center"><h3 class="text-2xl font-bold text-gray-900">${ssrInterpolate(plan.name.replace(" Annual", ""))}</h3><div class="mt-4"><span class="text-5xl font-extrabold text-gray-900"> $${ssrInterpolate(Math.floor(plan.price_in_dollars))}</span><span class="text-xl text-gray-600"> /${ssrInterpolate(plan.is_annual ? "year" : "month")}</span></div>`);
        if (plan.is_annual && plan.savings_percentage) {
          _push(`<div class="mt-2"><span class="text-sm text-green-600 font-medium"> Save ${ssrInterpolate(plan.savings_percentage)}% annually </span></div>`);
        } else {
          _push(`<!---->`);
        }
        if (plan.trial_period_days > 0) {
          _push(`<div class="mt-2"><span class="text-sm text-indigo-600 font-medium">${ssrInterpolate(plan.trial_period_days)}-day free trial </span></div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><ul class="mt-8 space-y-4"><!--[-->`);
        ssrRenderList(plan.features, (feature) => {
          _push(`<li class="flex items-start"><svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span class="ml-3 text-gray-700">${ssrInterpolate(feature)}</span></li>`);
        });
        _push(`<!--]--></ul><div class="mt-6 space-y-2"><div class="flex justify-between text-sm"><span class="text-gray-600">Analysis limit:</span><span class="font-medium text-gray-900">${ssrInterpolate(plan.analysis_limit === -1 ? "Unlimited" : plan.analysis_limit.toLocaleString())}</span></div><div class="flex justify-between text-sm"><span class="text-gray-600">Projects:</span><span class="font-medium text-gray-900">${ssrInterpolate(plan.project_limit === -1 ? "Unlimited" : plan.project_limit)}</span></div></div><div class="mt-8"><button class="${ssrRenderClass([
          "w-full py-3 px-4 rounded-lg font-medium text-sm transition-colors",
          plan.plan_tier === "professional" ? "bg-indigo-600 hover:bg-indigo-700 text-white" : "bg-gray-900 hover:bg-gray-800 text-white"
        ])}">${ssrInterpolate(_ctx.$page.props.auth.user ? "Choose Plan" : "Get Started")}</button></div></div>`);
      });
      _push(`<!--]--></div></div><div class="bg-white py-16"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="text-center mb-12"><h2 class="text-3xl font-extrabold text-gray-900"> Compare all features </h2><p class="mt-4 text-lg text-gray-600"> Everything you need to analyze blockchain data with confidence. </p></div><div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="text-left py-4 pr-4">Features</th><th class="text-center py-4 px-4">Starter</th><th class="text-center py-4 px-4">Professional</th><th class="text-center py-4 px-4">Enterprise</th></tr></thead><tbody class="divide-y divide-gray-200"><!--[-->`);
      ssrRenderList(comparisonFeatures, (feature) => {
        _push(`<tr><td class="py-4 pr-4 font-medium text-gray-900">${ssrInterpolate(feature.name)}</td><td class="text-center py-4 px-4">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(getFeatureComponent(feature.starter)), {
          value: feature.starter
        }, null), _parent);
        _push(`</td><td class="text-center py-4 px-4">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(getFeatureComponent(feature.professional)), {
          value: feature.professional
        }, null), _parent);
        _push(`</td><td class="text-center py-4 px-4">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(getFeatureComponent(feature.enterprise)), {
          value: feature.enterprise
        }, null), _parent);
        _push(`</td></tr>`);
      });
      _push(`<!--]--></tbody></table></div></div></div><div class="bg-gray-50 py-16"><div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8"><div class="text-center mb-12"><h2 class="text-3xl font-extrabold text-gray-900"> Frequently asked questions </h2></div><div class="space-y-8"><!--[-->`);
      ssrRenderList(faqs, (faq) => {
        _push(`<div><h3 class="text-lg font-medium text-gray-900 mb-2">${ssrInterpolate(faq.question)}</h3><p class="text-gray-600">${ssrInterpolate(faq.answer)}</p></div>`);
      });
      _push(`<!--]--></div></div></div></div><!--]-->`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Pricing.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
