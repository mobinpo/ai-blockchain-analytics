import { ref, computed, watch, onMounted, unref, mergeProps, withCtx, createVNode, createTextVNode, toDisplayString, createBlock, createCommentVNode, openBlock, withDirectives, vModelCheckbox, useSSRContext, Fragment, renderList } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrRenderClass, ssrRenderList } from "vue/server-renderer";
import { Head, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./AuthenticatedLayout-8TbwyeTu.js";
import { TransitionRoot, Dialog, TransitionChild, DialogPanel, DialogTitle } from "@headlessui/vue";
import { XMarkIcon, CreditCardIcon } from "@heroicons/vue/24/outline";
import { CheckIcon } from "@heroicons/vue/24/solid";
import "./ApplicationLogo-B2173abF.js";
import "./_plugin-vue_export-helper-1tPrXgE0.js";
const _sfc_main$1 = {
  __name: "PaymentModal",
  __ssrInlineRender: true,
  props: {
    show: Boolean,
    plan: Object,
    interval: String
  },
  emits: ["close", "success"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const emit = __emit;
    const stripeLoaded = ref(false);
    const stripe = ref(null);
    const cardElement = ref(null);
    const card = ref(null);
    const cardError = ref("");
    const processing = ref(false);
    const acceptedTerms = ref(false);
    const canSubmit = computed(() => {
      return stripeLoaded.value && acceptedTerms.value && !processing.value;
    });
    const getCurrentPrice = () => {
      if (!props.plan) return "0";
      return props.interval === "yearly" ? props.plan.yearly_price : props.plan.monthly_price;
    };
    const getYearlySavings = () => {
      if (!props.plan) return "0";
      const monthlyTotal = props.plan.monthly_price * 12;
      const yearlySavings = monthlyTotal - props.plan.yearly_price;
      return yearlySavings.toFixed(2);
    };
    const loadStripe = async () => {
      if (window.Stripe) {
        stripe.value = window.Stripe(void 0);
        stripeLoaded.value = true;
        setupCardElement();
        return;
      }
      const script = document.createElement("script");
      script.src = "https://js.stripe.com/v3/";
      script.onload = () => {
        stripe.value = window.Stripe(void 0);
        stripeLoaded.value = true;
        setupCardElement();
      };
      document.head.appendChild(script);
    };
    const setupCardElement = () => {
      if (!stripe.value || !cardElement.value) return;
      const elements = stripe.value.elements();
      card.value = elements.create("card", {
        style: {
          base: {
            fontSize: "16px",
            color: "#424770",
            "::placeholder": {
              color: "#aab7c4"
            }
          }
        }
      });
      card.value.mount(cardElement.value);
      card.value.addEventListener("change", (event) => {
        cardError.value = event.error ? event.error.message : "";
      });
    };
    const handleSubscribe = async () => {
      if (!stripe.value || !card.value || !props.plan) return;
      processing.value = true;
      cardError.value = "";
      try {
        const { error: methodError, paymentMethod } = await stripe.value.createPaymentMethod({
          type: "card",
          card: card.value
        });
        if (methodError) {
          cardError.value = methodError.message;
          processing.value = false;
          return;
        }
        const response = await fetch(route("billing.subscribe"), {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
          },
          body: JSON.stringify({
            plan: props.plan.key,
            interval: props.interval,
            payment_method: paymentMethod.id
          })
        });
        const result = await response.json();
        if (result.success) {
          emit("success", result);
        } else if (result.requires_action) {
          const { error: confirmError } = await stripe.value.confirmCardPayment(
            result.client_secret
          );
          if (confirmError) {
            cardError.value = confirmError.message;
          } else {
            emit("success", result);
          }
        } else {
          cardError.value = result.error || "An error occurred while processing your payment.";
        }
      } catch (error) {
        console.error("Payment error:", error);
        cardError.value = "An unexpected error occurred. Please try again.";
      } finally {
        processing.value = false;
      }
    };
    watch(() => props.show, (newShow) => {
      if (newShow && !stripeLoaded.value) {
        loadStripe();
      }
    });
    onMounted(() => {
      if (props.show) {
        loadStripe();
      }
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(unref(TransitionRoot), mergeProps({
        as: "template",
        show: __props.show
      }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Dialog), {
              as: "div",
              class: "relative z-50",
              onClose: ($event) => _ctx.$emit("close")
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(ssrRenderComponent(unref(TransitionChild), {
                    as: "template",
                    enter: "ease-out duration-300",
                    "enter-from": "opacity-0",
                    "enter-to": "opacity-100",
                    leave: "ease-in duration-200",
                    "leave-from": "opacity-100",
                    "leave-to": "opacity-0"
                  }, {
                    default: withCtx((_3, _push4, _parent4, _scopeId3) => {
                      if (_push4) {
                        _push4(`<div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"${_scopeId3}></div>`);
                      } else {
                        return [
                          createVNode("div", { class: "fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" })
                        ];
                      }
                    }),
                    _: 1
                  }, _parent3, _scopeId2));
                  _push3(`<div class="fixed inset-0 z-10 overflow-y-auto"${_scopeId2}><div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0"${_scopeId2}>`);
                  _push3(ssrRenderComponent(unref(TransitionChild), {
                    as: "template",
                    enter: "ease-out duration-300",
                    "enter-from": "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
                    "enter-to": "opacity-100 translate-y-0 sm:scale-100",
                    leave: "ease-in duration-200",
                    "leave-from": "opacity-100 translate-y-0 sm:scale-100",
                    "leave-to": "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                  }, {
                    default: withCtx((_3, _push4, _parent4, _scopeId3) => {
                      if (_push4) {
                        _push4(ssrRenderComponent(unref(DialogPanel), { class: "relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6" }, {
                          default: withCtx((_4, _push5, _parent5, _scopeId4) => {
                            var _a, _b, _c, _d;
                            if (_push5) {
                              _push5(`<div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block"${_scopeId4}><button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"${_scopeId4}><span class="sr-only"${_scopeId4}>Close</span>`);
                              _push5(ssrRenderComponent(unref(XMarkIcon), {
                                class: "h-6 w-6",
                                "aria-hidden": "true"
                              }, null, _parent5, _scopeId4));
                              _push5(`</button></div><div class="sm:flex sm:items-start"${_scopeId4}><div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10"${_scopeId4}>`);
                              _push5(ssrRenderComponent(unref(CreditCardIcon), {
                                class: "h-6 w-6 text-indigo-600",
                                "aria-hidden": "true"
                              }, null, _parent5, _scopeId4));
                              _push5(`</div><div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full"${_scopeId4}>`);
                              _push5(ssrRenderComponent(unref(DialogTitle), {
                                as: "h3",
                                class: "text-lg font-semibold leading-6 text-gray-900"
                              }, {
                                default: withCtx((_5, _push6, _parent6, _scopeId5) => {
                                  var _a2, _b2;
                                  if (_push6) {
                                    _push6(` Subscribe to ${ssrInterpolate((_a2 = __props.plan) == null ? void 0 : _a2.name)}`);
                                  } else {
                                    return [
                                      createTextVNode(" Subscribe to " + toDisplayString((_b2 = __props.plan) == null ? void 0 : _b2.name), 1)
                                    ];
                                  }
                                }),
                                _: 1
                              }, _parent5, _scopeId4));
                              _push5(`<div class="mt-2"${_scopeId4}><p class="text-sm text-gray-500"${_scopeId4}> You&#39;re about to subscribe to the ${ssrInterpolate((_a = __props.plan) == null ? void 0 : _a.name)} plan. </p><div class="mt-4 p-4 bg-gray-50 rounded-lg"${_scopeId4}><div class="flex justify-between items-center"${_scopeId4}><span class="font-medium"${_scopeId4}>${ssrInterpolate((_b = __props.plan) == null ? void 0 : _b.name)} Plan</span><span class="font-bold"${_scopeId4}> $${ssrInterpolate(getCurrentPrice())} <span class="text-sm font-normal text-gray-500"${_scopeId4}> /${ssrInterpolate(__props.interval)}</span></span></div>`);
                              if (__props.interval === "yearly") {
                                _push5(`<div class="text-sm text-green-600 mt-1"${_scopeId4}> Save $${ssrInterpolate(getYearlySavings())} compared to monthly billing </div>`);
                              } else {
                                _push5(`<!---->`);
                              }
                              _push5(`</div></div></div></div><div class="mt-6"${_scopeId4}>`);
                              if (!stripeLoaded.value) {
                                _push5(`<div class="text-center py-4"${_scopeId4}><div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"${_scopeId4}></div><p class="mt-2 text-sm text-gray-500"${_scopeId4}>Loading payment form...</p></div>`);
                              } else {
                                _push5(`<div${_scopeId4}><label class="block text-sm font-medium text-gray-700 mb-2"${_scopeId4}> Card Information </label><div class="p-3 border border-gray-300 rounded-md bg-white"${_scopeId4}></div>`);
                                if (cardError.value) {
                                  _push5(`<div class="mt-2 text-sm text-red-600"${_scopeId4}>${ssrInterpolate(cardError.value)}</div>`);
                                } else {
                                  _push5(`<!---->`);
                                }
                                _push5(`</div>`);
                              }
                              _push5(`</div><div class="mt-6"${_scopeId4}><div class="flex items-start"${_scopeId4}><input id="terms"${ssrIncludeBooleanAttr(Array.isArray(acceptedTerms.value) ? ssrLooseContain(acceptedTerms.value, null) : acceptedTerms.value) ? " checked" : ""} type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"${_scopeId4}><label for="terms" class="ml-2 block text-sm text-gray-700"${_scopeId4}> I agree to the <a href="#" class="text-indigo-600 hover:text-indigo-500"${_scopeId4}>Terms of Service</a> and <a href="#" class="text-indigo-600 hover:text-indigo-500"${_scopeId4}>Privacy Policy</a></label></div></div><div class="mt-6 flex flex-col sm:flex-row-reverse gap-3"${_scopeId4}><button type="button"${ssrIncludeBooleanAttr(!canSubmit.value || processing.value) ? " disabled" : ""} class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"${_scopeId4}>`);
                              if (processing.value) {
                                _push5(`<span${_scopeId4}>Processing...</span>`);
                              } else {
                                _push5(`<span${_scopeId4}>Subscribe Now</span>`);
                              }
                              _push5(`</button><button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"${_scopeId4}> Cancel </button></div>`);
                            } else {
                              return [
                                createVNode("div", { class: "absolute right-0 top-0 hidden pr-4 pt-4 sm:block" }, [
                                  createVNode("button", {
                                    type: "button",
                                    class: "rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
                                    onClick: ($event) => _ctx.$emit("close")
                                  }, [
                                    createVNode("span", { class: "sr-only" }, "Close"),
                                    createVNode(unref(XMarkIcon), {
                                      class: "h-6 w-6",
                                      "aria-hidden": "true"
                                    })
                                  ], 8, ["onClick"])
                                ]),
                                createVNode("div", { class: "sm:flex sm:items-start" }, [
                                  createVNode("div", { class: "mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10" }, [
                                    createVNode(unref(CreditCardIcon), {
                                      class: "h-6 w-6 text-indigo-600",
                                      "aria-hidden": "true"
                                    })
                                  ]),
                                  createVNode("div", { class: "mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full" }, [
                                    createVNode(unref(DialogTitle), {
                                      as: "h3",
                                      class: "text-lg font-semibold leading-6 text-gray-900"
                                    }, {
                                      default: withCtx(() => {
                                        var _a2;
                                        return [
                                          createTextVNode(" Subscribe to " + toDisplayString((_a2 = __props.plan) == null ? void 0 : _a2.name), 1)
                                        ];
                                      }),
                                      _: 1
                                    }),
                                    createVNode("div", { class: "mt-2" }, [
                                      createVNode("p", { class: "text-sm text-gray-500" }, " You're about to subscribe to the " + toDisplayString((_c = __props.plan) == null ? void 0 : _c.name) + " plan. ", 1),
                                      createVNode("div", { class: "mt-4 p-4 bg-gray-50 rounded-lg" }, [
                                        createVNode("div", { class: "flex justify-between items-center" }, [
                                          createVNode("span", { class: "font-medium" }, toDisplayString((_d = __props.plan) == null ? void 0 : _d.name) + " Plan", 1),
                                          createVNode("span", { class: "font-bold" }, [
                                            createTextVNode(" $" + toDisplayString(getCurrentPrice()) + " ", 1),
                                            createVNode("span", { class: "text-sm font-normal text-gray-500" }, " /" + toDisplayString(__props.interval), 1)
                                          ])
                                        ]),
                                        __props.interval === "yearly" ? (openBlock(), createBlock("div", {
                                          key: 0,
                                          class: "text-sm text-green-600 mt-1"
                                        }, " Save $" + toDisplayString(getYearlySavings()) + " compared to monthly billing ", 1)) : createCommentVNode("", true)
                                      ])
                                    ])
                                  ])
                                ]),
                                createVNode("div", { class: "mt-6" }, [
                                  !stripeLoaded.value ? (openBlock(), createBlock("div", {
                                    key: 0,
                                    class: "text-center py-4"
                                  }, [
                                    createVNode("div", { class: "inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600" }),
                                    createVNode("p", { class: "mt-2 text-sm text-gray-500" }, "Loading payment form...")
                                  ])) : (openBlock(), createBlock("div", { key: 1 }, [
                                    createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, " Card Information "),
                                    createVNode("div", {
                                      ref_key: "cardElement",
                                      ref: cardElement,
                                      class: "p-3 border border-gray-300 rounded-md bg-white"
                                    }, null, 512),
                                    cardError.value ? (openBlock(), createBlock("div", {
                                      key: 0,
                                      class: "mt-2 text-sm text-red-600"
                                    }, toDisplayString(cardError.value), 1)) : createCommentVNode("", true)
                                  ]))
                                ]),
                                createVNode("div", { class: "mt-6" }, [
                                  createVNode("div", { class: "flex items-start" }, [
                                    withDirectives(createVNode("input", {
                                      id: "terms",
                                      "onUpdate:modelValue": ($event) => acceptedTerms.value = $event,
                                      type: "checkbox",
                                      class: "h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                    }, null, 8, ["onUpdate:modelValue"]), [
                                      [vModelCheckbox, acceptedTerms.value]
                                    ]),
                                    createVNode("label", {
                                      for: "terms",
                                      class: "ml-2 block text-sm text-gray-700"
                                    }, [
                                      createTextVNode(" I agree to the "),
                                      createVNode("a", {
                                        href: "#",
                                        class: "text-indigo-600 hover:text-indigo-500"
                                      }, "Terms of Service"),
                                      createTextVNode(" and "),
                                      createVNode("a", {
                                        href: "#",
                                        class: "text-indigo-600 hover:text-indigo-500"
                                      }, "Privacy Policy")
                                    ])
                                  ])
                                ]),
                                createVNode("div", { class: "mt-6 flex flex-col sm:flex-row-reverse gap-3" }, [
                                  createVNode("button", {
                                    type: "button",
                                    disabled: !canSubmit.value || processing.value,
                                    onClick: handleSubscribe,
                                    class: "inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                                  }, [
                                    processing.value ? (openBlock(), createBlock("span", { key: 0 }, "Processing...")) : (openBlock(), createBlock("span", { key: 1 }, "Subscribe Now"))
                                  ], 8, ["disabled"]),
                                  createVNode("button", {
                                    type: "button",
                                    class: "mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto",
                                    onClick: ($event) => _ctx.$emit("close")
                                  }, " Cancel ", 8, ["onClick"])
                                ])
                              ];
                            }
                          }),
                          _: 1
                        }, _parent4, _scopeId3));
                      } else {
                        return [
                          createVNode(unref(DialogPanel), { class: "relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6" }, {
                            default: withCtx(() => {
                              var _a, _b;
                              return [
                                createVNode("div", { class: "absolute right-0 top-0 hidden pr-4 pt-4 sm:block" }, [
                                  createVNode("button", {
                                    type: "button",
                                    class: "rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
                                    onClick: ($event) => _ctx.$emit("close")
                                  }, [
                                    createVNode("span", { class: "sr-only" }, "Close"),
                                    createVNode(unref(XMarkIcon), {
                                      class: "h-6 w-6",
                                      "aria-hidden": "true"
                                    })
                                  ], 8, ["onClick"])
                                ]),
                                createVNode("div", { class: "sm:flex sm:items-start" }, [
                                  createVNode("div", { class: "mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10" }, [
                                    createVNode(unref(CreditCardIcon), {
                                      class: "h-6 w-6 text-indigo-600",
                                      "aria-hidden": "true"
                                    })
                                  ]),
                                  createVNode("div", { class: "mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full" }, [
                                    createVNode(unref(DialogTitle), {
                                      as: "h3",
                                      class: "text-lg font-semibold leading-6 text-gray-900"
                                    }, {
                                      default: withCtx(() => {
                                        var _a2;
                                        return [
                                          createTextVNode(" Subscribe to " + toDisplayString((_a2 = __props.plan) == null ? void 0 : _a2.name), 1)
                                        ];
                                      }),
                                      _: 1
                                    }),
                                    createVNode("div", { class: "mt-2" }, [
                                      createVNode("p", { class: "text-sm text-gray-500" }, " You're about to subscribe to the " + toDisplayString((_a = __props.plan) == null ? void 0 : _a.name) + " plan. ", 1),
                                      createVNode("div", { class: "mt-4 p-4 bg-gray-50 rounded-lg" }, [
                                        createVNode("div", { class: "flex justify-between items-center" }, [
                                          createVNode("span", { class: "font-medium" }, toDisplayString((_b = __props.plan) == null ? void 0 : _b.name) + " Plan", 1),
                                          createVNode("span", { class: "font-bold" }, [
                                            createTextVNode(" $" + toDisplayString(getCurrentPrice()) + " ", 1),
                                            createVNode("span", { class: "text-sm font-normal text-gray-500" }, " /" + toDisplayString(__props.interval), 1)
                                          ])
                                        ]),
                                        __props.interval === "yearly" ? (openBlock(), createBlock("div", {
                                          key: 0,
                                          class: "text-sm text-green-600 mt-1"
                                        }, " Save $" + toDisplayString(getYearlySavings()) + " compared to monthly billing ", 1)) : createCommentVNode("", true)
                                      ])
                                    ])
                                  ])
                                ]),
                                createVNode("div", { class: "mt-6" }, [
                                  !stripeLoaded.value ? (openBlock(), createBlock("div", {
                                    key: 0,
                                    class: "text-center py-4"
                                  }, [
                                    createVNode("div", { class: "inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600" }),
                                    createVNode("p", { class: "mt-2 text-sm text-gray-500" }, "Loading payment form...")
                                  ])) : (openBlock(), createBlock("div", { key: 1 }, [
                                    createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, " Card Information "),
                                    createVNode("div", {
                                      ref_key: "cardElement",
                                      ref: cardElement,
                                      class: "p-3 border border-gray-300 rounded-md bg-white"
                                    }, null, 512),
                                    cardError.value ? (openBlock(), createBlock("div", {
                                      key: 0,
                                      class: "mt-2 text-sm text-red-600"
                                    }, toDisplayString(cardError.value), 1)) : createCommentVNode("", true)
                                  ]))
                                ]),
                                createVNode("div", { class: "mt-6" }, [
                                  createVNode("div", { class: "flex items-start" }, [
                                    withDirectives(createVNode("input", {
                                      id: "terms",
                                      "onUpdate:modelValue": ($event) => acceptedTerms.value = $event,
                                      type: "checkbox",
                                      class: "h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                    }, null, 8, ["onUpdate:modelValue"]), [
                                      [vModelCheckbox, acceptedTerms.value]
                                    ]),
                                    createVNode("label", {
                                      for: "terms",
                                      class: "ml-2 block text-sm text-gray-700"
                                    }, [
                                      createTextVNode(" I agree to the "),
                                      createVNode("a", {
                                        href: "#",
                                        class: "text-indigo-600 hover:text-indigo-500"
                                      }, "Terms of Service"),
                                      createTextVNode(" and "),
                                      createVNode("a", {
                                        href: "#",
                                        class: "text-indigo-600 hover:text-indigo-500"
                                      }, "Privacy Policy")
                                    ])
                                  ])
                                ]),
                                createVNode("div", { class: "mt-6 flex flex-col sm:flex-row-reverse gap-3" }, [
                                  createVNode("button", {
                                    type: "button",
                                    disabled: !canSubmit.value || processing.value,
                                    onClick: handleSubscribe,
                                    class: "inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                                  }, [
                                    processing.value ? (openBlock(), createBlock("span", { key: 0 }, "Processing...")) : (openBlock(), createBlock("span", { key: 1 }, "Subscribe Now"))
                                  ], 8, ["disabled"]),
                                  createVNode("button", {
                                    type: "button",
                                    class: "mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto",
                                    onClick: ($event) => _ctx.$emit("close")
                                  }, " Cancel ", 8, ["onClick"])
                                ])
                              ];
                            }),
                            _: 1
                          })
                        ];
                      }
                    }),
                    _: 1
                  }, _parent3, _scopeId2));
                  _push3(`</div></div>`);
                } else {
                  return [
                    createVNode(unref(TransitionChild), {
                      as: "template",
                      enter: "ease-out duration-300",
                      "enter-from": "opacity-0",
                      "enter-to": "opacity-100",
                      leave: "ease-in duration-200",
                      "leave-from": "opacity-100",
                      "leave-to": "opacity-0"
                    }, {
                      default: withCtx(() => [
                        createVNode("div", { class: "fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" })
                      ]),
                      _: 1
                    }),
                    createVNode("div", { class: "fixed inset-0 z-10 overflow-y-auto" }, [
                      createVNode("div", { class: "flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0" }, [
                        createVNode(unref(TransitionChild), {
                          as: "template",
                          enter: "ease-out duration-300",
                          "enter-from": "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
                          "enter-to": "opacity-100 translate-y-0 sm:scale-100",
                          leave: "ease-in duration-200",
                          "leave-from": "opacity-100 translate-y-0 sm:scale-100",
                          "leave-to": "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        }, {
                          default: withCtx(() => [
                            createVNode(unref(DialogPanel), { class: "relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6" }, {
                              default: withCtx(() => {
                                var _a, _b;
                                return [
                                  createVNode("div", { class: "absolute right-0 top-0 hidden pr-4 pt-4 sm:block" }, [
                                    createVNode("button", {
                                      type: "button",
                                      class: "rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
                                      onClick: ($event) => _ctx.$emit("close")
                                    }, [
                                      createVNode("span", { class: "sr-only" }, "Close"),
                                      createVNode(unref(XMarkIcon), {
                                        class: "h-6 w-6",
                                        "aria-hidden": "true"
                                      })
                                    ], 8, ["onClick"])
                                  ]),
                                  createVNode("div", { class: "sm:flex sm:items-start" }, [
                                    createVNode("div", { class: "mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10" }, [
                                      createVNode(unref(CreditCardIcon), {
                                        class: "h-6 w-6 text-indigo-600",
                                        "aria-hidden": "true"
                                      })
                                    ]),
                                    createVNode("div", { class: "mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full" }, [
                                      createVNode(unref(DialogTitle), {
                                        as: "h3",
                                        class: "text-lg font-semibold leading-6 text-gray-900"
                                      }, {
                                        default: withCtx(() => {
                                          var _a2;
                                          return [
                                            createTextVNode(" Subscribe to " + toDisplayString((_a2 = __props.plan) == null ? void 0 : _a2.name), 1)
                                          ];
                                        }),
                                        _: 1
                                      }),
                                      createVNode("div", { class: "mt-2" }, [
                                        createVNode("p", { class: "text-sm text-gray-500" }, " You're about to subscribe to the " + toDisplayString((_a = __props.plan) == null ? void 0 : _a.name) + " plan. ", 1),
                                        createVNode("div", { class: "mt-4 p-4 bg-gray-50 rounded-lg" }, [
                                          createVNode("div", { class: "flex justify-between items-center" }, [
                                            createVNode("span", { class: "font-medium" }, toDisplayString((_b = __props.plan) == null ? void 0 : _b.name) + " Plan", 1),
                                            createVNode("span", { class: "font-bold" }, [
                                              createTextVNode(" $" + toDisplayString(getCurrentPrice()) + " ", 1),
                                              createVNode("span", { class: "text-sm font-normal text-gray-500" }, " /" + toDisplayString(__props.interval), 1)
                                            ])
                                          ]),
                                          __props.interval === "yearly" ? (openBlock(), createBlock("div", {
                                            key: 0,
                                            class: "text-sm text-green-600 mt-1"
                                          }, " Save $" + toDisplayString(getYearlySavings()) + " compared to monthly billing ", 1)) : createCommentVNode("", true)
                                        ])
                                      ])
                                    ])
                                  ]),
                                  createVNode("div", { class: "mt-6" }, [
                                    !stripeLoaded.value ? (openBlock(), createBlock("div", {
                                      key: 0,
                                      class: "text-center py-4"
                                    }, [
                                      createVNode("div", { class: "inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600" }),
                                      createVNode("p", { class: "mt-2 text-sm text-gray-500" }, "Loading payment form...")
                                    ])) : (openBlock(), createBlock("div", { key: 1 }, [
                                      createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, " Card Information "),
                                      createVNode("div", {
                                        ref_key: "cardElement",
                                        ref: cardElement,
                                        class: "p-3 border border-gray-300 rounded-md bg-white"
                                      }, null, 512),
                                      cardError.value ? (openBlock(), createBlock("div", {
                                        key: 0,
                                        class: "mt-2 text-sm text-red-600"
                                      }, toDisplayString(cardError.value), 1)) : createCommentVNode("", true)
                                    ]))
                                  ]),
                                  createVNode("div", { class: "mt-6" }, [
                                    createVNode("div", { class: "flex items-start" }, [
                                      withDirectives(createVNode("input", {
                                        id: "terms",
                                        "onUpdate:modelValue": ($event) => acceptedTerms.value = $event,
                                        type: "checkbox",
                                        class: "h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                      }, null, 8, ["onUpdate:modelValue"]), [
                                        [vModelCheckbox, acceptedTerms.value]
                                      ]),
                                      createVNode("label", {
                                        for: "terms",
                                        class: "ml-2 block text-sm text-gray-700"
                                      }, [
                                        createTextVNode(" I agree to the "),
                                        createVNode("a", {
                                          href: "#",
                                          class: "text-indigo-600 hover:text-indigo-500"
                                        }, "Terms of Service"),
                                        createTextVNode(" and "),
                                        createVNode("a", {
                                          href: "#",
                                          class: "text-indigo-600 hover:text-indigo-500"
                                        }, "Privacy Policy")
                                      ])
                                    ])
                                  ]),
                                  createVNode("div", { class: "mt-6 flex flex-col sm:flex-row-reverse gap-3" }, [
                                    createVNode("button", {
                                      type: "button",
                                      disabled: !canSubmit.value || processing.value,
                                      onClick: handleSubscribe,
                                      class: "inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                                    }, [
                                      processing.value ? (openBlock(), createBlock("span", { key: 0 }, "Processing...")) : (openBlock(), createBlock("span", { key: 1 }, "Subscribe Now"))
                                    ], 8, ["disabled"]),
                                    createVNode("button", {
                                      type: "button",
                                      class: "mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto",
                                      onClick: ($event) => _ctx.$emit("close")
                                    }, " Cancel ", 8, ["onClick"])
                                  ])
                                ];
                              }),
                              _: 1
                            })
                          ]),
                          _: 1
                        })
                      ])
                    ])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(unref(Dialog), {
                as: "div",
                class: "relative z-50",
                onClose: ($event) => _ctx.$emit("close")
              }, {
                default: withCtx(() => [
                  createVNode(unref(TransitionChild), {
                    as: "template",
                    enter: "ease-out duration-300",
                    "enter-from": "opacity-0",
                    "enter-to": "opacity-100",
                    leave: "ease-in duration-200",
                    "leave-from": "opacity-100",
                    "leave-to": "opacity-0"
                  }, {
                    default: withCtx(() => [
                      createVNode("div", { class: "fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" })
                    ]),
                    _: 1
                  }),
                  createVNode("div", { class: "fixed inset-0 z-10 overflow-y-auto" }, [
                    createVNode("div", { class: "flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0" }, [
                      createVNode(unref(TransitionChild), {
                        as: "template",
                        enter: "ease-out duration-300",
                        "enter-from": "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
                        "enter-to": "opacity-100 translate-y-0 sm:scale-100",
                        leave: "ease-in duration-200",
                        "leave-from": "opacity-100 translate-y-0 sm:scale-100",
                        "leave-to": "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                      }, {
                        default: withCtx(() => [
                          createVNode(unref(DialogPanel), { class: "relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6" }, {
                            default: withCtx(() => {
                              var _a, _b;
                              return [
                                createVNode("div", { class: "absolute right-0 top-0 hidden pr-4 pt-4 sm:block" }, [
                                  createVNode("button", {
                                    type: "button",
                                    class: "rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
                                    onClick: ($event) => _ctx.$emit("close")
                                  }, [
                                    createVNode("span", { class: "sr-only" }, "Close"),
                                    createVNode(unref(XMarkIcon), {
                                      class: "h-6 w-6",
                                      "aria-hidden": "true"
                                    })
                                  ], 8, ["onClick"])
                                ]),
                                createVNode("div", { class: "sm:flex sm:items-start" }, [
                                  createVNode("div", { class: "mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10" }, [
                                    createVNode(unref(CreditCardIcon), {
                                      class: "h-6 w-6 text-indigo-600",
                                      "aria-hidden": "true"
                                    })
                                  ]),
                                  createVNode("div", { class: "mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full" }, [
                                    createVNode(unref(DialogTitle), {
                                      as: "h3",
                                      class: "text-lg font-semibold leading-6 text-gray-900"
                                    }, {
                                      default: withCtx(() => {
                                        var _a2;
                                        return [
                                          createTextVNode(" Subscribe to " + toDisplayString((_a2 = __props.plan) == null ? void 0 : _a2.name), 1)
                                        ];
                                      }),
                                      _: 1
                                    }),
                                    createVNode("div", { class: "mt-2" }, [
                                      createVNode("p", { class: "text-sm text-gray-500" }, " You're about to subscribe to the " + toDisplayString((_a = __props.plan) == null ? void 0 : _a.name) + " plan. ", 1),
                                      createVNode("div", { class: "mt-4 p-4 bg-gray-50 rounded-lg" }, [
                                        createVNode("div", { class: "flex justify-between items-center" }, [
                                          createVNode("span", { class: "font-medium" }, toDisplayString((_b = __props.plan) == null ? void 0 : _b.name) + " Plan", 1),
                                          createVNode("span", { class: "font-bold" }, [
                                            createTextVNode(" $" + toDisplayString(getCurrentPrice()) + " ", 1),
                                            createVNode("span", { class: "text-sm font-normal text-gray-500" }, " /" + toDisplayString(__props.interval), 1)
                                          ])
                                        ]),
                                        __props.interval === "yearly" ? (openBlock(), createBlock("div", {
                                          key: 0,
                                          class: "text-sm text-green-600 mt-1"
                                        }, " Save $" + toDisplayString(getYearlySavings()) + " compared to monthly billing ", 1)) : createCommentVNode("", true)
                                      ])
                                    ])
                                  ])
                                ]),
                                createVNode("div", { class: "mt-6" }, [
                                  !stripeLoaded.value ? (openBlock(), createBlock("div", {
                                    key: 0,
                                    class: "text-center py-4"
                                  }, [
                                    createVNode("div", { class: "inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600" }),
                                    createVNode("p", { class: "mt-2 text-sm text-gray-500" }, "Loading payment form...")
                                  ])) : (openBlock(), createBlock("div", { key: 1 }, [
                                    createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, " Card Information "),
                                    createVNode("div", {
                                      ref_key: "cardElement",
                                      ref: cardElement,
                                      class: "p-3 border border-gray-300 rounded-md bg-white"
                                    }, null, 512),
                                    cardError.value ? (openBlock(), createBlock("div", {
                                      key: 0,
                                      class: "mt-2 text-sm text-red-600"
                                    }, toDisplayString(cardError.value), 1)) : createCommentVNode("", true)
                                  ]))
                                ]),
                                createVNode("div", { class: "mt-6" }, [
                                  createVNode("div", { class: "flex items-start" }, [
                                    withDirectives(createVNode("input", {
                                      id: "terms",
                                      "onUpdate:modelValue": ($event) => acceptedTerms.value = $event,
                                      type: "checkbox",
                                      class: "h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                    }, null, 8, ["onUpdate:modelValue"]), [
                                      [vModelCheckbox, acceptedTerms.value]
                                    ]),
                                    createVNode("label", {
                                      for: "terms",
                                      class: "ml-2 block text-sm text-gray-700"
                                    }, [
                                      createTextVNode(" I agree to the "),
                                      createVNode("a", {
                                        href: "#",
                                        class: "text-indigo-600 hover:text-indigo-500"
                                      }, "Terms of Service"),
                                      createTextVNode(" and "),
                                      createVNode("a", {
                                        href: "#",
                                        class: "text-indigo-600 hover:text-indigo-500"
                                      }, "Privacy Policy")
                                    ])
                                  ])
                                ]),
                                createVNode("div", { class: "mt-6 flex flex-col sm:flex-row-reverse gap-3" }, [
                                  createVNode("button", {
                                    type: "button",
                                    disabled: !canSubmit.value || processing.value,
                                    onClick: handleSubscribe,
                                    class: "inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                                  }, [
                                    processing.value ? (openBlock(), createBlock("span", { key: 0 }, "Processing...")) : (openBlock(), createBlock("span", { key: 1 }, "Subscribe Now"))
                                  ], 8, ["disabled"]),
                                  createVNode("button", {
                                    type: "button",
                                    class: "mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto",
                                    onClick: ($event) => _ctx.$emit("close")
                                  }, " Cancel ", 8, ["onClick"])
                                ])
                              ];
                            }),
                            _: 1
                          })
                        ]),
                        _: 1
                      })
                    ])
                  ])
                ]),
                _: 1
              }, 8, ["onClose"])
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Billing/PaymentModal.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = {
  __name: "Plans",
  __ssrInlineRender: true,
  props: {
    plans: Object,
    currentPlan: String
  },
  setup(__props) {
    const props = __props;
    const billingInterval = ref("monthly");
    const processing = ref(false);
    const selectedPlan = ref(null);
    const showPaymentModal = ref(false);
    const selectedPlanData = ref(null);
    const currentPlan = computed(() => props.currentPlan);
    const faqs = [
      {
        question: "Can I change my plan at any time?",
        answer: "Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately and your billing will be prorated accordingly."
      },
      {
        question: "What happens if I exceed my plan limits?",
        answer: "If you exceed your plan limits, additional usage will be charged at our standard overage rates. You'll receive notifications when approaching your limits."
      },
      {
        question: "Do you offer refunds?",
        answer: "We offer a 14-day money-back guarantee for new subscriptions. Contact our support team if you're not satisfied with our service."
      },
      {
        question: "Can I cancel my subscription?",
        answer: "Yes, you can cancel your subscription at any time. You'll continue to have access to your plan's features until the end of your billing period."
      },
      {
        question: "What payment methods do you accept?",
        answer: "We accept all major credit cards (Visa, MasterCard, American Express) and bank transfers for enterprise customers."
      }
    ];
    const getCurrentPrice = (plan) => {
      return billingInterval.value === "yearly" ? plan.yearly_price : plan.monthly_price;
    };
    const formatLimit = (limit) => {
      if (limit === "Unlimited") return limit;
      if (typeof limit === "string" && limit.includes("K")) return limit;
      if (typeof limit === "string" && limit.includes("M")) return limit;
      return (limit == null ? void 0 : limit.toLocaleString()) || "0";
    };
    const isCurrentPlan = (planKey) => {
      return currentPlan.value === planKey;
    };
    const getButtonText = (planKey) => {
      if (!currentPlan.value) {
        return "Get Started";
      }
      const planOrder = { starter: 1, professional: 2, enterprise: 3 };
      const currentOrder = planOrder[currentPlan.value] || 0;
      const selectedOrder = planOrder[planKey] || 0;
      if (selectedOrder > currentOrder) {
        return "Upgrade";
      } else if (selectedOrder < currentOrder) {
        return "Downgrade";
      }
      return "Select Plan";
    };
    const selectPlan = (planKey) => {
      selectedPlan.value = planKey;
      selectedPlanData.value = props.plans[planKey];
      showPaymentModal.value = true;
    };
    const handlePaymentSuccess = (response) => {
      showPaymentModal.value = false;
      processing.value = false;
      router.visit(route("billing.index"), {
        onSuccess: () => {
          console.log("Subscription updated successfully!");
        }
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$2, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Subscription Plans" }, null, _parent2, _scopeId));
            _push2(`<div class="min-h-screen bg-gray-50 py-8"${_scopeId}><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"${_scopeId}><div class="text-center mb-12"${_scopeId}><h2 class="text-3xl font-bold text-gray-900 sm:text-4xl"${_scopeId}> Choose Your Plan </h2><p class="mt-4 text-lg text-gray-600"${_scopeId}> Select the perfect plan for your blockchain analysis needs </p></div><div class="flex justify-center mb-8"${_scopeId}><div class="flex items-center bg-white rounded-lg p-1 shadow-sm"${_scopeId}><button class="${ssrRenderClass([
              "px-4 py-2 text-sm font-medium rounded-md transition-all duration-200",
              billingInterval.value === "monthly" ? "bg-indigo-600 text-white shadow-sm" : "text-gray-500 hover:text-gray-700"
            ])}"${_scopeId}> Monthly </button><button class="${ssrRenderClass([
              "px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 relative",
              billingInterval.value === "yearly" ? "bg-indigo-600 text-white shadow-sm" : "text-gray-500 hover:text-gray-700"
            ])}"${_scopeId}> Yearly <span class="absolute -top-1 -right-1 bg-green-500 text-white text-xs px-1 py-0.5 rounded-full"${_scopeId}> Save 17% </span></button></div></div><div class="grid grid-cols-1 gap-8 lg:grid-cols-3"${_scopeId}><!--[-->`);
            ssrRenderList(__props.plans, (plan, planKey) => {
              _push2(`<div class="${ssrRenderClass([
                "rounded-2xl shadow-xl relative",
                planKey === "professional" ? "border-2 border-indigo-600 bg-white transform scale-105 z-10" : "border border-gray-200 bg-white"
              ])}"${_scopeId}>`);
              if (planKey === "professional") {
                _push2(`<div class="absolute -top-4 left-1/2 transform -translate-x-1/2"${_scopeId}><span class="bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-medium"${_scopeId}> Most Popular </span></div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<div class="p-8"${_scopeId}><div class="text-center"${_scopeId}><h3 class="text-2xl font-bold text-gray-900"${_scopeId}>${ssrInterpolate(plan.name)}</h3><p class="mt-2 text-gray-600"${_scopeId}>${ssrInterpolate(plan.description)}</p><div class="mt-6"${_scopeId}><div class="flex items-center justify-center"${_scopeId}><span class="text-4xl font-bold text-gray-900"${_scopeId}> $${ssrInterpolate(getCurrentPrice(plan))}</span><span class="text-gray-600 ml-2"${_scopeId}> /${ssrInterpolate(billingInterval.value === "yearly" ? "year" : "month")}</span></div>`);
              if (billingInterval.value === "yearly") {
                _push2(`<div class="mt-1 text-sm text-gray-500"${_scopeId}> $${ssrInterpolate((plan.yearly_price / 12).toFixed(2))}/month billed annually </div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div></div><div class="mt-8"${_scopeId}><ul class="space-y-4"${_scopeId}><!--[-->`);
              ssrRenderList(plan.features_list.Features, (feature) => {
                _push2(`<li class="flex items-start"${_scopeId}>`);
                _push2(ssrRenderComponent(unref(CheckIcon), { class: "h-5 w-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" }, null, _parent2, _scopeId));
                _push2(`<span class="text-gray-700"${_scopeId}>${ssrInterpolate(feature)}</span></li>`);
              });
              _push2(`<!--]--></ul></div><div class="mt-6 pt-6 border-t border-gray-200"${_scopeId}><div class="grid grid-cols-1 gap-3 text-sm"${_scopeId}><div class="flex justify-between"${_scopeId}><span class="text-gray-600"${_scopeId}>Analyses per month</span><span class="font-medium text-gray-900"${_scopeId}>${ssrInterpolate(formatLimit(plan.features_list["Analysis per month"]))}</span></div><div class="flex justify-between"${_scopeId}><span class="text-gray-600"${_scopeId}>API calls per month</span><span class="font-medium text-gray-900"${_scopeId}>${ssrInterpolate(formatLimit(plan.features_list["API calls per month"]))}</span></div><div class="flex justify-between"${_scopeId}><span class="text-gray-600"${_scopeId}>AI tokens per month</span><span class="font-medium text-gray-900"${_scopeId}>${ssrInterpolate(formatLimit(plan.features_list["AI tokens per month"]))}</span></div><div class="flex justify-between"${_scopeId}><span class="text-gray-600"${_scopeId}>Projects</span><span class="font-medium text-gray-900"${_scopeId}>${ssrInterpolate(formatLimit(plan.features_list["Projects"]))}</span></div><div class="flex justify-between"${_scopeId}><span class="text-gray-600"${_scopeId}>Support</span><span class="font-medium text-gray-900"${_scopeId}>${ssrInterpolate(plan.features_list["Support"])}</span></div></div></div><div class="mt-8"${_scopeId}>`);
              if (!isCurrentPlan(planKey)) {
                _push2(`<button${ssrIncludeBooleanAttr(processing.value) ? " disabled" : ""} class="${ssrRenderClass([
                  "w-full py-3 px-4 rounded-lg font-semibold text-center transition-all duration-200 disabled:opacity-50",
                  planKey === "professional" ? "bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg" : "bg-indigo-600 text-white hover:bg-indigo-700"
                ])}"${_scopeId}>`);
                if (processing.value && selectedPlan.value === planKey) {
                  _push2(`<span${_scopeId}> Processing... </span>`);
                } else {
                  _push2(`<span${_scopeId}>${ssrInterpolate(getButtonText(planKey))}</span>`);
                }
                _push2(`</button>`);
              } else {
                _push2(`<div class="w-full py-3 px-4 rounded-lg font-semibold text-center bg-gray-100 text-gray-600"${_scopeId}> Current Plan </div>`);
              }
              _push2(`</div></div></div>`);
            });
            _push2(`<!--]--></div><div class="mt-12 max-w-md mx-auto"${_scopeId}><div class="bg-gray-100 rounded-lg p-6 text-center"${_scopeId}><h3 class="text-lg font-semibold text-gray-900 mb-2"${_scopeId}> Free Tier </h3><p class="text-gray-600 mb-4"${_scopeId}> Try our platform with limited features </p><div class="text-sm text-gray-600 space-y-1"${_scopeId}><div${_scopeId}>3 analyses per month</div><div${_scopeId}>100 API calls per month</div><div${_scopeId}>10K AI tokens per month</div><div${_scopeId}>2 projects</div><div${_scopeId}>Community support</div></div>`);
            if (!currentPlan.value) {
              _push2(`<div class="mt-4 text-green-600 font-medium"${_scopeId}> You&#39;re currently on the free tier </div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div><div class="mt-16 max-w-3xl mx-auto"${_scopeId}><h3 class="text-2xl font-bold text-gray-900 text-center mb-8"${_scopeId}> Frequently Asked Questions </h3><div class="space-y-6"${_scopeId}><!--[-->`);
            ssrRenderList(faqs, (faq) => {
              _push2(`<div class="bg-white rounded-lg p-6 shadow-sm"${_scopeId}><h4 class="font-semibold text-gray-900 mb-2"${_scopeId}>${ssrInterpolate(faq.question)}</h4><p class="text-gray-600"${_scopeId}>${ssrInterpolate(faq.answer)}</p></div>`);
            });
            _push2(`<!--]--></div></div></div></div>`);
            _push2(ssrRenderComponent(_sfc_main$1, {
              show: showPaymentModal.value,
              plan: selectedPlanData.value,
              interval: billingInterval.value,
              onClose: ($event) => showPaymentModal.value = false,
              onSuccess: handlePaymentSuccess
            }, null, _parent2, _scopeId));
          } else {
            return [
              createVNode(unref(Head), { title: "Subscription Plans" }),
              createVNode("div", { class: "min-h-screen bg-gray-50 py-8" }, [
                createVNode("div", { class: "max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "text-center mb-12" }, [
                    createVNode("h2", { class: "text-3xl font-bold text-gray-900 sm:text-4xl" }, " Choose Your Plan "),
                    createVNode("p", { class: "mt-4 text-lg text-gray-600" }, " Select the perfect plan for your blockchain analysis needs ")
                  ]),
                  createVNode("div", { class: "flex justify-center mb-8" }, [
                    createVNode("div", { class: "flex items-center bg-white rounded-lg p-1 shadow-sm" }, [
                      createVNode("button", {
                        onClick: ($event) => billingInterval.value = "monthly",
                        class: [
                          "px-4 py-2 text-sm font-medium rounded-md transition-all duration-200",
                          billingInterval.value === "monthly" ? "bg-indigo-600 text-white shadow-sm" : "text-gray-500 hover:text-gray-700"
                        ]
                      }, " Monthly ", 10, ["onClick"]),
                      createVNode("button", {
                        onClick: ($event) => billingInterval.value = "yearly",
                        class: [
                          "px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 relative",
                          billingInterval.value === "yearly" ? "bg-indigo-600 text-white shadow-sm" : "text-gray-500 hover:text-gray-700"
                        ]
                      }, [
                        createTextVNode(" Yearly "),
                        createVNode("span", { class: "absolute -top-1 -right-1 bg-green-500 text-white text-xs px-1 py-0.5 rounded-full" }, " Save 17% ")
                      ], 10, ["onClick"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 gap-8 lg:grid-cols-3" }, [
                    (openBlock(true), createBlock(Fragment, null, renderList(__props.plans, (plan, planKey) => {
                      return openBlock(), createBlock("div", {
                        key: planKey,
                        class: [
                          "rounded-2xl shadow-xl relative",
                          planKey === "professional" ? "border-2 border-indigo-600 bg-white transform scale-105 z-10" : "border border-gray-200 bg-white"
                        ]
                      }, [
                        planKey === "professional" ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "absolute -top-4 left-1/2 transform -translate-x-1/2"
                        }, [
                          createVNode("span", { class: "bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-medium" }, " Most Popular ")
                        ])) : createCommentVNode("", true),
                        createVNode("div", { class: "p-8" }, [
                          createVNode("div", { class: "text-center" }, [
                            createVNode("h3", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(plan.name), 1),
                            createVNode("p", { class: "mt-2 text-gray-600" }, toDisplayString(plan.description), 1),
                            createVNode("div", { class: "mt-6" }, [
                              createVNode("div", { class: "flex items-center justify-center" }, [
                                createVNode("span", { class: "text-4xl font-bold text-gray-900" }, " $" + toDisplayString(getCurrentPrice(plan)), 1),
                                createVNode("span", { class: "text-gray-600 ml-2" }, " /" + toDisplayString(billingInterval.value === "yearly" ? "year" : "month"), 1)
                              ]),
                              billingInterval.value === "yearly" ? (openBlock(), createBlock("div", {
                                key: 0,
                                class: "mt-1 text-sm text-gray-500"
                              }, " $" + toDisplayString((plan.yearly_price / 12).toFixed(2)) + "/month billed annually ", 1)) : createCommentVNode("", true)
                            ])
                          ]),
                          createVNode("div", { class: "mt-8" }, [
                            createVNode("ul", { class: "space-y-4" }, [
                              (openBlock(true), createBlock(Fragment, null, renderList(plan.features_list.Features, (feature) => {
                                return openBlock(), createBlock("li", {
                                  key: feature,
                                  class: "flex items-start"
                                }, [
                                  createVNode(unref(CheckIcon), { class: "h-5 w-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" }),
                                  createVNode("span", { class: "text-gray-700" }, toDisplayString(feature), 1)
                                ]);
                              }), 128))
                            ])
                          ]),
                          createVNode("div", { class: "mt-6 pt-6 border-t border-gray-200" }, [
                            createVNode("div", { class: "grid grid-cols-1 gap-3 text-sm" }, [
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-gray-600" }, "Analyses per month"),
                                createVNode("span", { class: "font-medium text-gray-900" }, toDisplayString(formatLimit(plan.features_list["Analysis per month"])), 1)
                              ]),
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-gray-600" }, "API calls per month"),
                                createVNode("span", { class: "font-medium text-gray-900" }, toDisplayString(formatLimit(plan.features_list["API calls per month"])), 1)
                              ]),
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-gray-600" }, "AI tokens per month"),
                                createVNode("span", { class: "font-medium text-gray-900" }, toDisplayString(formatLimit(plan.features_list["AI tokens per month"])), 1)
                              ]),
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-gray-600" }, "Projects"),
                                createVNode("span", { class: "font-medium text-gray-900" }, toDisplayString(formatLimit(plan.features_list["Projects"])), 1)
                              ]),
                              createVNode("div", { class: "flex justify-between" }, [
                                createVNode("span", { class: "text-gray-600" }, "Support"),
                                createVNode("span", { class: "font-medium text-gray-900" }, toDisplayString(plan.features_list["Support"]), 1)
                              ])
                            ])
                          ]),
                          createVNode("div", { class: "mt-8" }, [
                            !isCurrentPlan(planKey) ? (openBlock(), createBlock("button", {
                              key: 0,
                              onClick: ($event) => selectPlan(planKey),
                              disabled: processing.value,
                              class: [
                                "w-full py-3 px-4 rounded-lg font-semibold text-center transition-all duration-200 disabled:opacity-50",
                                planKey === "professional" ? "bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg" : "bg-indigo-600 text-white hover:bg-indigo-700"
                              ]
                            }, [
                              processing.value && selectedPlan.value === planKey ? (openBlock(), createBlock("span", { key: 0 }, " Processing... ")) : (openBlock(), createBlock("span", { key: 1 }, toDisplayString(getButtonText(planKey)), 1))
                            ], 10, ["onClick", "disabled"])) : (openBlock(), createBlock("div", {
                              key: 1,
                              class: "w-full py-3 px-4 rounded-lg font-semibold text-center bg-gray-100 text-gray-600"
                            }, " Current Plan "))
                          ])
                        ])
                      ], 2);
                    }), 128))
                  ]),
                  createVNode("div", { class: "mt-12 max-w-md mx-auto" }, [
                    createVNode("div", { class: "bg-gray-100 rounded-lg p-6 text-center" }, [
                      createVNode("h3", { class: "text-lg font-semibold text-gray-900 mb-2" }, " Free Tier "),
                      createVNode("p", { class: "text-gray-600 mb-4" }, " Try our platform with limited features "),
                      createVNode("div", { class: "text-sm text-gray-600 space-y-1" }, [
                        createVNode("div", null, "3 analyses per month"),
                        createVNode("div", null, "100 API calls per month"),
                        createVNode("div", null, "10K AI tokens per month"),
                        createVNode("div", null, "2 projects"),
                        createVNode("div", null, "Community support")
                      ]),
                      !currentPlan.value ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mt-4 text-green-600 font-medium"
                      }, " You're currently on the free tier ")) : createCommentVNode("", true)
                    ])
                  ]),
                  createVNode("div", { class: "mt-16 max-w-3xl mx-auto" }, [
                    createVNode("h3", { class: "text-2xl font-bold text-gray-900 text-center mb-8" }, " Frequently Asked Questions "),
                    createVNode("div", { class: "space-y-6" }, [
                      (openBlock(), createBlock(Fragment, null, renderList(faqs, (faq) => {
                        return createVNode("div", {
                          key: faq.question,
                          class: "bg-white rounded-lg p-6 shadow-sm"
                        }, [
                          createVNode("h4", { class: "font-semibold text-gray-900 mb-2" }, toDisplayString(faq.question), 1),
                          createVNode("p", { class: "text-gray-600" }, toDisplayString(faq.answer), 1)
                        ]);
                      }), 64))
                    ])
                  ])
                ])
              ]),
              createVNode(_sfc_main$1, {
                show: showPaymentModal.value,
                plan: selectedPlanData.value,
                interval: billingInterval.value,
                onClose: ($event) => showPaymentModal.value = false,
                onSuccess: handlePaymentSuccess
              }, null, 8, ["show", "plan", "interval", "onClose"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Billing/Plans.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
