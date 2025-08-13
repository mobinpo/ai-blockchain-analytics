import { onMounted, withCtx, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./AppLayout-UOYGqPAE.js";
import "@inertiajs/vue3";
const _sfc_main = {
  __name: "CssTest",
  __ssrInlineRender: true,
  setup(__props) {
    onMounted(() => {
      console.log("🎨 CSS Test page mounted");
      const testElement = document.createElement("div");
      testElement.className = "css-loading-test";
      testElement.style.cssText = "position: fixed; top: 10px; right: 10px; background: green; color: white; padding: 5px; z-index: 9999; font-size: 12px;";
      testElement.textContent = "CSS Loaded ✅";
      document.body.appendChild(testElement);
      const hasBackgroundGray = getComputedStyle(document.body).backgroundColor;
      console.log("📊 Body background color:", hasBackgroundGray);
      setTimeout(() => {
        document.body.removeChild(testElement);
      }, 3e3);
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="css-loading-test fixed top-4 left-4 bg-green-500 text-white p-2 rounded shadow-lg z-50"${_scopeId}><span class="text-sm font-medium"${_scopeId}>✅ CSS is loading properly!</span></div><div class="space-y-8"${_scopeId}><div class="text-center"${_scopeId}><h1 class="text-4xl font-bold text-gray-900 mb-4"${_scopeId}>CSS Layout Test</h1><p class="text-lg text-gray-600"${_scopeId}>Testing Tailwind CSS classes and layout components</p></div><div class="card"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Grid Layout Test</h2><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"${_scopeId}><div class="bg-blue-100 p-4 rounded-lg"${_scopeId}><h3 class="font-semibold text-blue-900"${_scopeId}>Grid Item 1</h3><p class="text-blue-700"${_scopeId}>This should be in a responsive grid</p></div><div class="bg-green-100 p-4 rounded-lg"${_scopeId}><h3 class="font-semibold text-green-900"${_scopeId}>Grid Item 2</h3><p class="text-green-700"${_scopeId}>Grid should adapt to screen size</p></div><div class="bg-purple-100 p-4 rounded-lg"${_scopeId}><h3 class="font-semibold text-purple-900"${_scopeId}>Grid Item 3</h3><p class="text-purple-700"${_scopeId}>Colors should be working</p></div></div></div><div class="card"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Flexbox Layout Test</h2><div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><div class="w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center"${_scopeId}><span class="text-white font-bold"${_scopeId}>A</span></div><div${_scopeId}><h3 class="font-semibold text-gray-900"${_scopeId}>Flex Item</h3><p class="text-sm text-gray-600"${_scopeId}>Should be aligned properly</p></div></div><button class="btn btn-primary"${_scopeId}>Action Button</button></div></div><div class="card relative"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Z-Index and Positioning Test</h2><div class="relative h-32 bg-gray-100 rounded-lg overflow-hidden"${_scopeId}><div class="absolute inset-0 bg-blue-200 opacity-50"${_scopeId}></div><div class="absolute top-4 left-4 z-10 bg-white p-3 rounded shadow-lg"${_scopeId}><p class="text-sm font-medium"${_scopeId}>This should be on top</p></div><div class="absolute bottom-4 right-4 bg-red-500 text-white p-2 rounded"${_scopeId}><p class="text-xs"${_scopeId}>Bottom right</p></div></div></div><div class="card"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Typography Test</h2><div class="space-y-4"${_scopeId}><h1 class="text-4xl font-bold text-gray-900"${_scopeId}>Heading 1</h1><h2 class="text-3xl font-semibold text-gray-800"${_scopeId}>Heading 2</h2><h3 class="text-2xl font-medium text-gray-700"${_scopeId}>Heading 3</h3><p class="text-base text-gray-600 leading-relaxed"${_scopeId}> This is a paragraph with normal text. It should have proper line height and spacing. The font should be readable and well-styled. This text should wrap properly and maintain good readability across different screen sizes. </p><p class="text-sm text-gray-500"${_scopeId}>Small text for captions or metadata</p></div></div><div class="card"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Color Palette Test</h2><div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2"${_scopeId}><div class="bg-red-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Red</span></div><div class="bg-blue-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Blue</span></div><div class="bg-green-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Green</span></div><div class="bg-yellow-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Yellow</span></div><div class="bg-purple-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Purple</span></div><div class="bg-pink-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Pink</span></div><div class="bg-indigo-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Indigo</span></div><div class="bg-gray-500 h-16 rounded flex items-center justify-center"${_scopeId}><span class="text-white text-xs font-medium"${_scopeId}>Gray</span></div></div></div><div class="card"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Responsive Design Test</h2><div class="space-y-4"${_scopeId}><div class="block sm:hidden bg-red-100 p-4 rounded"${_scopeId}><p class="text-red-800 font-medium"${_scopeId}>Visible only on mobile (sm and below)</p></div><div class="hidden sm:block md:hidden bg-yellow-100 p-4 rounded"${_scopeId}><p class="text-yellow-800 font-medium"${_scopeId}>Visible only on small tablets (sm to md)</p></div><div class="hidden md:block lg:hidden bg-blue-100 p-4 rounded"${_scopeId}><p class="text-blue-800 font-medium"${_scopeId}>Visible only on tablets (md to lg)</p></div><div class="hidden lg:block bg-green-100 p-4 rounded"${_scopeId}><p class="text-green-800 font-medium"${_scopeId}>Visible only on desktop (lg and above)</p></div></div></div><div class="card bg-gray-50"${_scopeId}><h2 class="text-2xl font-semibold text-gray-900 mb-4"${_scopeId}>Debug Information</h2><div class="text-sm space-y-2 font-mono"${_scopeId}><p${_scopeId}><strong${_scopeId}>Screen Size:</strong> Check the responsive boxes above</p><p${_scopeId}><strong${_scopeId}>CSS Status:</strong> If you can see colors and proper spacing, Tailwind is working!</p><p${_scopeId}><strong${_scopeId}>Layout Status:</strong> Check that the sidebar doesn&#39;t overlap this content</p><p${_scopeId}><strong${_scopeId}>Z-Index:</strong> The white box in the positioning test should be on top</p></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "css-loading-test fixed top-4 left-4 bg-green-500 text-white p-2 rounded shadow-lg z-50" }, [
                createVNode("span", { class: "text-sm font-medium" }, "✅ CSS is loading properly!")
              ]),
              createVNode("div", { class: "space-y-8" }, [
                createVNode("div", { class: "text-center" }, [
                  createVNode("h1", { class: "text-4xl font-bold text-gray-900 mb-4" }, "CSS Layout Test"),
                  createVNode("p", { class: "text-lg text-gray-600" }, "Testing Tailwind CSS classes and layout components")
                ]),
                createVNode("div", { class: "card" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Grid Layout Test"),
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" }, [
                    createVNode("div", { class: "bg-blue-100 p-4 rounded-lg" }, [
                      createVNode("h3", { class: "font-semibold text-blue-900" }, "Grid Item 1"),
                      createVNode("p", { class: "text-blue-700" }, "This should be in a responsive grid")
                    ]),
                    createVNode("div", { class: "bg-green-100 p-4 rounded-lg" }, [
                      createVNode("h3", { class: "font-semibold text-green-900" }, "Grid Item 2"),
                      createVNode("p", { class: "text-green-700" }, "Grid should adapt to screen size")
                    ]),
                    createVNode("div", { class: "bg-purple-100 p-4 rounded-lg" }, [
                      createVNode("h3", { class: "font-semibold text-purple-900" }, "Grid Item 3"),
                      createVNode("p", { class: "text-purple-700" }, "Colors should be working")
                    ])
                  ])
                ]),
                createVNode("div", { class: "card" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Flexbox Layout Test"),
                  createVNode("div", { class: "flex flex-col sm:flex-row items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("div", { class: "w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center" }, [
                        createVNode("span", { class: "text-white font-bold" }, "A")
                      ]),
                      createVNode("div", null, [
                        createVNode("h3", { class: "font-semibold text-gray-900" }, "Flex Item"),
                        createVNode("p", { class: "text-sm text-gray-600" }, "Should be aligned properly")
                      ])
                    ]),
                    createVNode("button", { class: "btn btn-primary" }, "Action Button")
                  ])
                ]),
                createVNode("div", { class: "card relative" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Z-Index and Positioning Test"),
                  createVNode("div", { class: "relative h-32 bg-gray-100 rounded-lg overflow-hidden" }, [
                    createVNode("div", { class: "absolute inset-0 bg-blue-200 opacity-50" }),
                    createVNode("div", { class: "absolute top-4 left-4 z-10 bg-white p-3 rounded shadow-lg" }, [
                      createVNode("p", { class: "text-sm font-medium" }, "This should be on top")
                    ]),
                    createVNode("div", { class: "absolute bottom-4 right-4 bg-red-500 text-white p-2 rounded" }, [
                      createVNode("p", { class: "text-xs" }, "Bottom right")
                    ])
                  ])
                ]),
                createVNode("div", { class: "card" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Typography Test"),
                  createVNode("div", { class: "space-y-4" }, [
                    createVNode("h1", { class: "text-4xl font-bold text-gray-900" }, "Heading 1"),
                    createVNode("h2", { class: "text-3xl font-semibold text-gray-800" }, "Heading 2"),
                    createVNode("h3", { class: "text-2xl font-medium text-gray-700" }, "Heading 3"),
                    createVNode("p", { class: "text-base text-gray-600 leading-relaxed" }, " This is a paragraph with normal text. It should have proper line height and spacing. The font should be readable and well-styled. This text should wrap properly and maintain good readability across different screen sizes. "),
                    createVNode("p", { class: "text-sm text-gray-500" }, "Small text for captions or metadata")
                  ])
                ]),
                createVNode("div", { class: "card" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Color Palette Test"),
                  createVNode("div", { class: "grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2" }, [
                    createVNode("div", { class: "bg-red-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Red")
                    ]),
                    createVNode("div", { class: "bg-blue-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Blue")
                    ]),
                    createVNode("div", { class: "bg-green-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Green")
                    ]),
                    createVNode("div", { class: "bg-yellow-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Yellow")
                    ]),
                    createVNode("div", { class: "bg-purple-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Purple")
                    ]),
                    createVNode("div", { class: "bg-pink-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Pink")
                    ]),
                    createVNode("div", { class: "bg-indigo-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Indigo")
                    ]),
                    createVNode("div", { class: "bg-gray-500 h-16 rounded flex items-center justify-center" }, [
                      createVNode("span", { class: "text-white text-xs font-medium" }, "Gray")
                    ])
                  ])
                ]),
                createVNode("div", { class: "card" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Responsive Design Test"),
                  createVNode("div", { class: "space-y-4" }, [
                    createVNode("div", { class: "block sm:hidden bg-red-100 p-4 rounded" }, [
                      createVNode("p", { class: "text-red-800 font-medium" }, "Visible only on mobile (sm and below)")
                    ]),
                    createVNode("div", { class: "hidden sm:block md:hidden bg-yellow-100 p-4 rounded" }, [
                      createVNode("p", { class: "text-yellow-800 font-medium" }, "Visible only on small tablets (sm to md)")
                    ]),
                    createVNode("div", { class: "hidden md:block lg:hidden bg-blue-100 p-4 rounded" }, [
                      createVNode("p", { class: "text-blue-800 font-medium" }, "Visible only on tablets (md to lg)")
                    ]),
                    createVNode("div", { class: "hidden lg:block bg-green-100 p-4 rounded" }, [
                      createVNode("p", { class: "text-green-800 font-medium" }, "Visible only on desktop (lg and above)")
                    ])
                  ])
                ]),
                createVNode("div", { class: "card bg-gray-50" }, [
                  createVNode("h2", { class: "text-2xl font-semibold text-gray-900 mb-4" }, "Debug Information"),
                  createVNode("div", { class: "text-sm space-y-2 font-mono" }, [
                    createVNode("p", null, [
                      createVNode("strong", null, "Screen Size:"),
                      createTextVNode(" Check the responsive boxes above")
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "CSS Status:"),
                      createTextVNode(" If you can see colors and proper spacing, Tailwind is working!")
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Layout Status:"),
                      createTextVNode(" Check that the sidebar doesn't overlap this content")
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Z-Index:"),
                      createTextVNode(" The white box in the positioning test should be on top")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/CssTest.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
