import { ref, onMounted, onUnmounted, computed, withCtx, createVNode, toDisplayString, withDirectives, createBlock, openBlock, Fragment, renderList, vModelSelect, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderClass, ssrRenderStyle } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./AppLayout-UOYGqPAE.js";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-1tPrXgE0.js";
import "@inertiajs/vue3";
const maxFailedUpdates = 3;
const _sfc_main = {
  __name: "Sentiment",
  __ssrInlineRender: true,
  setup(__props) {
    const selectedTimeframe = ref("24h");
    ref("all");
    const timeframes = [
      { value: "1h", label: "Last Hour" },
      { value: "24h", label: "Last 24 Hours" },
      { value: "7d", label: "Last 7 Days" },
      { value: "30d", label: "Last 30 Days" }
    ];
    const protocolSentiments = ref([
      {
        protocol: "Uniswap",
        current: 0.73,
        change: 0.05,
        trend: "up",
        volume: 1247,
        sources: ["Twitter", "Reddit", "Discord", "Telegram"],
        lastUpdated: "2 minutes ago",
        color: "bg-green-500",
        details: {
          positive: 73,
          neutral: 19,
          negative: 8
        }
      },
      {
        protocol: "Aave",
        current: 0.85,
        change: 0.12,
        trend: "up",
        volume: 892,
        sources: ["Twitter", "Reddit", "Medium"],
        lastUpdated: "3 minutes ago",
        color: "bg-green-600",
        details: {
          positive: 85,
          neutral: 12,
          negative: 3
        }
      },
      {
        protocol: "Compound",
        current: 0.42,
        change: -0.08,
        trend: "down",
        volume: 634,
        sources: ["Twitter", "Discord"],
        lastUpdated: "1 minute ago",
        color: "bg-yellow-500",
        details: {
          positive: 42,
          neutral: 35,
          negative: 23
        }
      },
      {
        protocol: "MakerDAO",
        current: 0.68,
        change: 0.02,
        trend: "up",
        volume: 456,
        sources: ["Twitter", "Reddit"],
        lastUpdated: "4 minutes ago",
        color: "bg-green-400",
        details: {
          positive: 68,
          neutral: 22,
          negative: 10
        }
      }
    ]);
    const recentMentions = ref([
      {
        id: 1,
        protocol: "Uniswap",
        platform: "Twitter",
        author: "@DefiWhale",
        content: "Uniswap V4 hooks are revolutionary! This will change the DeFi landscape forever. Amazing innovation from the team. 🚀",
        sentiment: 0.92,
        timestamp: "5 minutes ago",
        engagement: { likes: 247, retweets: 89, replies: 34 }
      },
      {
        id: 2,
        protocol: "Aave",
        platform: "Reddit",
        author: "u/CryptoAnalyst",
        content: "AAVE's new governance proposal looks solid. The risk parameters are well thought out and the community response has been overwhelmingly positive.",
        sentiment: 0.78,
        timestamp: "12 minutes ago",
        engagement: { likes: 156, retweets: 0, replies: 23 }
      },
      {
        id: 3,
        protocol: "Compound",
        platform: "Discord",
        author: "LiquidityProvider#1234",
        content: "Not sure about the latest Compound update. Gas fees seem higher and the UI changes are confusing. Hope they fix these issues soon.",
        sentiment: 0.15,
        timestamp: "18 minutes ago",
        engagement: { likes: 12, retweets: 0, replies: 8 }
      },
      {
        id: 4,
        protocol: "MakerDAO",
        platform: "Twitter",
        author: "@DeFiResearcher",
        content: "MakerDAO's stability through market volatility continues to impress. The DAI peg mechanism is working beautifully.",
        sentiment: 0.84,
        timestamp: "25 minutes ago",
        engagement: { likes: 98, retweets: 45, replies: 12 }
      }
    ]);
    const trendingTopics = ref([
      { topic: "#UniswapV4", sentiment: 0.89, mentions: 1247, change: "+15%" },
      { topic: "#LiquidStaking", sentiment: 0.76, mentions: 892, change: "+8%" },
      { topic: "#DeFiSummer", sentiment: 0.82, mentions: 634, change: "+22%" },
      { topic: "#YieldFarming", sentiment: 0.58, mentions: 456, change: "-3%" },
      { topic: "#CrossChain", sentiment: 0.71, mentions: 334, change: "+12%" }
    ]);
    const getSentimentColor = (sentiment) => {
      if (sentiment >= 0.7) return "text-green-600 bg-green-50";
      if (sentiment >= 0.5) return "text-yellow-600 bg-yellow-50";
      return "text-red-600 bg-red-50";
    };
    const getSentimentLabel = (sentiment) => {
      if (sentiment >= 0.7) return "Positive";
      if (sentiment >= 0.5) return "Neutral";
      return "Negative";
    };
    const getPlatformIcon = (platform) => {
      const icons = {
        "Twitter": "🐦",
        "Reddit": "🔴",
        "Discord": "💬",
        "Telegram": "✈️",
        "Medium": "📝"
      };
      return icons[platform] || "💬";
    };
    let sentimentUpdateInterval = null;
    let apiConnectionTimeout = null;
    const isComponentActive = ref(true);
    const apiConnectionState = ref("connected");
    const failedUpdateCount = ref(0);
    const updateSentimentData = async () => {
      try {
        if (!isComponentActive.value) {
          console.log("⏹️ Sentiment component inactive, skipping update");
          return;
        }
        console.log("📊 Updating sentiment data...");
        if (Math.random() < 0.1) {
          throw new Error("Sentiment API temporarily unavailable");
        }
        if (Math.random() < 0.05) {
          throw new Error("Network timeout while fetching sentiment data");
        }
        protocolSentiments.value.forEach((protocol) => {
          const change = (Math.random() - 0.5) * 0.02;
          protocol.current = Math.max(0, Math.min(1, protocol.current + change));
          protocol.lastUpdated = "Just now";
          protocol.change = (Math.random() - 0.5) * 0.05;
          protocol.trend = protocol.change >= 0 ? "up" : "down";
        });
        trendingTopics.value.forEach((topic) => {
          if (Math.random() > 0.7) {
            topic.mentions += Math.floor((Math.random() - 0.5) * 50);
            topic.mentions = Math.max(100, topic.mentions);
            topic.sentiment = Math.max(0, Math.min(1, topic.sentiment + (Math.random() - 0.5) * 0.1));
          }
        });
        failedUpdateCount.value = 0;
        apiConnectionState.value = "connected";
        console.log("✅ Sentiment data updated successfully");
      } catch (error) {
        failedUpdateCount.value++;
        console.error(`❌ Sentiment update failed (${failedUpdateCount.value}/${maxFailedUpdates}):`, error);
        if (failedUpdateCount.value >= maxFailedUpdates) {
          console.error("🚫 Max sentiment update failures reached, stopping updates");
          apiConnectionState.value = "failed";
          if (sentimentUpdateInterval) {
            clearInterval(sentimentUpdateInterval);
            sentimentUpdateInterval = null;
          }
          apiConnectionTimeout = setTimeout(() => {
            if (isComponentActive.value) {
              console.log("🔄 Attempting to reconnect sentiment updates...");
              failedUpdateCount.value = 0;
              apiConnectionState.value = "reconnecting";
              startSentimentUpdates();
            }
          }, 3e4);
        } else {
          apiConnectionState.value = "unstable";
        }
      }
    };
    const startSentimentUpdates = () => {
      if (sentimentUpdateInterval) {
        clearInterval(sentimentUpdateInterval);
      }
      console.log("🚀 Starting sentiment updates");
      sentimentUpdateInterval = setInterval(() => {
        if (isComponentActive.value && apiConnectionState.value !== "failed") {
          updateSentimentData();
        }
      }, 1e4);
    };
    onMounted(() => {
      console.log("💭 Sentiment component mounted");
      isComponentActive.value = true;
      updateSentimentData().then(() => {
        if (apiConnectionState.value === "connected") {
          startSentimentUpdates();
        }
      });
      const handleVisibilityChange = () => {
        if (document.hidden) {
          console.log("📱 Page hidden, pausing sentiment updates");
          if (sentimentUpdateInterval) {
            clearInterval(sentimentUpdateInterval);
            sentimentUpdateInterval = null;
          }
        } else {
          console.log("📱 Page visible, resuming sentiment updates");
          if (isComponentActive.value && apiConnectionState.value !== "failed") {
            startSentimentUpdates();
          }
        }
      };
      document.addEventListener("visibilitychange", handleVisibilityChange);
      onUnmounted(() => {
        console.log("🧹 Sentiment component unmounting...");
        isComponentActive.value = false;
        if (sentimentUpdateInterval) {
          clearInterval(sentimentUpdateInterval);
          sentimentUpdateInterval = null;
        }
        if (apiConnectionTimeout) {
          clearTimeout(apiConnectionTimeout);
          apiConnectionTimeout = null;
        }
        document.removeEventListener("visibilitychange", handleVisibilityChange);
        console.log("✅ Sentiment cleanup completed");
      });
    });
    const overallSentiment = computed(() => {
      const avg = protocolSentiments.value.reduce((sum, p) => sum + p.current, 0) / protocolSentiments.value.length;
      return Math.round(avg * 100) / 100;
    });
    const totalMentions = computed(() => {
      return protocolSentiments.value.reduce((sum, p) => sum + p.volume, 0);
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="mb-8" data-v-24a359e4${_scopeId}><h1 class="text-3xl font-bold text-gray-900 mb-2" data-v-24a359e4${_scopeId}>Sentiment Analysis</h1><p class="text-gray-600" data-v-24a359e4${_scopeId}>Real-time market sentiment tracking across social media and news platforms</p></div><div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" data-v-24a359e4${_scopeId}><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><p class="text-sm text-gray-600 mb-1" data-v-24a359e4${_scopeId}>Overall Sentiment</p><p class="text-2xl font-bold text-gray-900" data-v-24a359e4${_scopeId}>${ssrInterpolate(Math.round(overallSentiment.value * 100))}%</p><div class="flex items-center mt-2" data-v-24a359e4${_scopeId}><span class="text-green-600 text-sm font-medium" data-v-24a359e4${_scopeId}>+5.2%</span><span class="text-xs text-gray-500 ml-1" data-v-24a359e4${_scopeId}>vs yesterday</span></div></div><div class="text-3xl" data-v-24a359e4${_scopeId}>📈</div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><p class="text-sm text-gray-600 mb-1" data-v-24a359e4${_scopeId}>Total Mentions</p><p class="text-2xl font-bold text-gray-900" data-v-24a359e4${_scopeId}>${ssrInterpolate(totalMentions.value.toLocaleString())}</p><div class="flex items-center mt-2" data-v-24a359e4${_scopeId}><span class="text-green-600 text-sm font-medium" data-v-24a359e4${_scopeId}>+12.5%</span><span class="text-xs text-gray-500 ml-1" data-v-24a359e4${_scopeId}>vs yesterday</span></div></div><div class="text-3xl" data-v-24a359e4${_scopeId}>💬</div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><p class="text-sm text-gray-600 mb-1" data-v-24a359e4${_scopeId}>Positive Sentiment</p><p class="text-2xl font-bold text-green-600" data-v-24a359e4${_scopeId}>${ssrInterpolate(Math.round(protocolSentiments.value.filter((p) => p.current >= 0.7).length / protocolSentiments.value.length * 100))}%</p><div class="flex items-center mt-2" data-v-24a359e4${_scopeId}><span class="text-green-600 text-sm font-medium" data-v-24a359e4${_scopeId}>+8.1%</span><span class="text-xs text-gray-500 ml-1" data-v-24a359e4${_scopeId}>vs yesterday</span></div></div><div class="text-3xl" data-v-24a359e4${_scopeId}>😊</div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><p class="text-sm text-gray-600 mb-1" data-v-24a359e4${_scopeId}>Active Sources</p><p class="text-2xl font-bold text-gray-900" data-v-24a359e4${_scopeId}>12</p><div class="flex items-center mt-2" data-v-24a359e4${_scopeId}><span class="text-green-600 text-sm font-medium" data-v-24a359e4${_scopeId}>+2</span><span class="text-xs text-gray-500 ml-1" data-v-24a359e4${_scopeId}>new sources</span></div></div><div class="text-3xl" data-v-24a359e4${_scopeId}>🔍</div></div></div></div><div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8" data-v-24a359e4${_scopeId}><div class="flex flex-wrap items-center gap-4" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-2" data-v-24a359e4${_scopeId}>Timeframe</label><select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" data-v-24a359e4${_scopeId}><!--[-->`);
            ssrRenderList(timeframes, (timeframe) => {
              _push2(`<option${ssrRenderAttr("value", timeframe.value)} data-v-24a359e4${ssrIncludeBooleanAttr(Array.isArray(selectedTimeframe.value) ? ssrLooseContain(selectedTimeframe.value, timeframe.value) : ssrLooseEqual(selectedTimeframe.value, timeframe.value)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(timeframe.label)}</option>`);
            });
            _push2(`<!--]--></select></div><div class="flex-1" data-v-24a359e4${_scopeId}></div><div class="flex items-center space-x-2" data-v-24a359e4${_scopeId}><div class="flex items-center space-x-2 rounded-full bg-green-50 px-3 py-1" data-v-24a359e4${_scopeId}><div class="h-2 w-2 rounded-full bg-green-500 animate-pulse" data-v-24a359e4${_scopeId}></div><span class="text-sm font-medium text-green-700" data-v-24a359e4${_scopeId}>Live Updates</span></div></div></div></div><div class="grid grid-cols-1 lg:grid-cols-3 gap-8" data-v-24a359e4${_scopeId}><div class="lg:col-span-2 space-y-6" data-v-24a359e4${_scopeId}><div class="bg-white rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="px-6 py-4 border-b border-gray-200" data-v-24a359e4${_scopeId}><h2 class="text-lg font-semibold text-gray-900" data-v-24a359e4${_scopeId}>Protocol Sentiment</h2></div><div class="p-6" data-v-24a359e4${_scopeId}><div class="space-y-6" data-v-24a359e4${_scopeId}><!--[-->`);
            ssrRenderList(protocolSentiments.value, (protocol) => {
              _push2(`<div class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between mb-4" data-v-24a359e4${_scopeId}><div class="flex items-center space-x-3" data-v-24a359e4${_scopeId}><h3 class="text-lg font-semibold text-gray-900" data-v-24a359e4${_scopeId}>${ssrInterpolate(protocol.protocol)}</h3><span class="${ssrRenderClass([getSentimentColor(protocol.current), "px-3 py-1 text-xs font-medium rounded-full"])}" data-v-24a359e4${_scopeId}>${ssrInterpolate(getSentimentLabel(protocol.current))}</span></div><div class="text-right" data-v-24a359e4${_scopeId}><div class="text-2xl font-bold text-gray-900" data-v-24a359e4${_scopeId}>${ssrInterpolate(Math.round(protocol.current * 100))}%</div><div class="flex items-center text-sm" data-v-24a359e4${_scopeId}><span class="${ssrRenderClass(protocol.change >= 0 ? "text-green-600" : "text-red-600")}" data-v-24a359e4${_scopeId}>${ssrInterpolate(protocol.change >= 0 ? "+" : "")}${ssrInterpolate(Math.round(protocol.change * 100))}% </span><span class="text-gray-500 ml-1" data-v-24a359e4${_scopeId}>${ssrInterpolate(selectedTimeframe.value)}</span></div></div></div><div class="mb-4" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between text-sm text-gray-600 mb-2" data-v-24a359e4${_scopeId}><span data-v-24a359e4${_scopeId}>Sentiment Breakdown</span><span data-v-24a359e4${_scopeId}>${ssrInterpolate(protocol.volume)} mentions</span></div><div class="flex w-full bg-gray-200 rounded-full h-3" data-v-24a359e4${_scopeId}><div class="bg-green-500 h-3 rounded-l-full transition-all duration-300" style="${ssrRenderStyle({ width: protocol.details.positive + "%" })}" data-v-24a359e4${_scopeId}></div><div class="bg-yellow-500 h-3 transition-all duration-300" style="${ssrRenderStyle({ width: protocol.details.neutral + "%" })}" data-v-24a359e4${_scopeId}></div><div class="bg-red-500 h-3 rounded-r-full transition-all duration-300" style="${ssrRenderStyle({ width: protocol.details.negative + "%" })}" data-v-24a359e4${_scopeId}></div></div><div class="flex justify-between text-xs text-gray-500 mt-1" data-v-24a359e4${_scopeId}><span data-v-24a359e4${_scopeId}>${ssrInterpolate(protocol.details.positive)}% Positive</span><span data-v-24a359e4${_scopeId}>${ssrInterpolate(protocol.details.neutral)}% Neutral</span><span data-v-24a359e4${_scopeId}>${ssrInterpolate(protocol.details.negative)}% Negative</span></div></div><div class="flex items-center justify-between text-sm text-gray-600" data-v-24a359e4${_scopeId}><div class="flex items-center space-x-2" data-v-24a359e4${_scopeId}><span data-v-24a359e4${_scopeId}>Sources:</span><div class="flex space-x-1" data-v-24a359e4${_scopeId}><!--[-->`);
              ssrRenderList(protocol.sources, (source) => {
                _push2(`<span class="px-2 py-1 bg-gray-200 rounded text-xs" data-v-24a359e4${_scopeId}>${ssrInterpolate(getPlatformIcon(source))} ${ssrInterpolate(source)}</span>`);
              });
              _push2(`<!--]--></div></div><span data-v-24a359e4${_scopeId}>Updated ${ssrInterpolate(protocol.lastUpdated)}</span></div></div>`);
            });
            _push2(`<!--]--></div></div></div></div><div class="space-y-6" data-v-24a359e4${_scopeId}><div class="bg-white rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="px-6 py-4 border-b border-gray-200" data-v-24a359e4${_scopeId}><h2 class="text-lg font-semibold text-gray-900" data-v-24a359e4${_scopeId}>Trending Topics</h2></div><div class="p-6" data-v-24a359e4${_scopeId}><div class="space-y-4" data-v-24a359e4${_scopeId}><!--[-->`);
            ssrRenderList(trendingTopics.value, (topic) => {
              _push2(`<div class="flex items-center justify-between p-3 bg-gray-50 rounded-md" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><div class="font-medium text-gray-900" data-v-24a359e4${_scopeId}>${ssrInterpolate(topic.topic)}</div><div class="text-sm text-gray-600" data-v-24a359e4${_scopeId}>${ssrInterpolate(topic.mentions)} mentions</div></div><div class="text-right" data-v-24a359e4${_scopeId}><div class="${ssrRenderClass([getSentimentColor(topic.sentiment), "px-2 py-1 text-xs font-medium rounded-full mb-1"])}" data-v-24a359e4${_scopeId}>${ssrInterpolate(Math.round(topic.sentiment * 100))}% </div><div class="${ssrRenderClass([topic.change.startsWith("+") ? "text-green-600" : "text-red-600", "text-xs font-medium"])}" data-v-24a359e4${_scopeId}>${ssrInterpolate(topic.change)}</div></div></div>`);
            });
            _push2(`<!--]--></div></div></div><div class="bg-white rounded-lg shadow-sm border border-gray-200" data-v-24a359e4${_scopeId}><div class="px-6 py-4 border-b border-gray-200" data-v-24a359e4${_scopeId}><h2 class="text-lg font-semibold text-gray-900" data-v-24a359e4${_scopeId}>Recent Mentions</h2></div><div class="p-6" data-v-24a359e4${_scopeId}><div class="space-y-4 max-h-96 overflow-y-auto" data-v-24a359e4${_scopeId}><!--[-->`);
            ssrRenderList(recentMentions.value, (mention) => {
              _push2(`<div class="p-3 bg-gray-50 rounded-md border border-gray-100" data-v-24a359e4${_scopeId}><div class="flex items-start justify-between mb-2" data-v-24a359e4${_scopeId}><div class="flex items-center space-x-2" data-v-24a359e4${_scopeId}><span data-v-24a359e4${_scopeId}>${ssrInterpolate(getPlatformIcon(mention.platform))}</span><span class="font-medium text-sm text-gray-900" data-v-24a359e4${_scopeId}>${ssrInterpolate(mention.author)}</span><span class="text-xs text-gray-500" data-v-24a359e4${_scopeId}>${ssrInterpolate(mention.protocol)}</span></div><span class="${ssrRenderClass([getSentimentColor(mention.sentiment), "px-2 py-1 text-xs font-medium rounded-full"])}" data-v-24a359e4${_scopeId}>${ssrInterpolate(Math.round(mention.sentiment * 100))}% </span></div><p class="text-sm text-gray-700 mb-2 line-clamp-3" data-v-24a359e4${_scopeId}>${ssrInterpolate(mention.content)}</p><div class="flex items-center justify-between text-xs text-gray-500" data-v-24a359e4${_scopeId}><span data-v-24a359e4${_scopeId}>${ssrInterpolate(mention.timestamp)}</span><div class="flex space-x-2" data-v-24a359e4${_scopeId}><span data-v-24a359e4${_scopeId}>👍 ${ssrInterpolate(mention.engagement.likes)}</span>`);
              if (mention.engagement.retweets > 0) {
                _push2(`<span data-v-24a359e4${_scopeId}>🔄 ${ssrInterpolate(mention.engagement.retweets)}</span>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<span data-v-24a359e4${_scopeId}>💬 ${ssrInterpolate(mention.engagement.replies)}</span></div></div></div>`);
            });
            _push2(`<!--]--></div></div></div></div></div><div class="mt-8 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg p-6 text-white" data-v-24a359e4${_scopeId}><div class="flex items-center justify-between" data-v-24a359e4${_scopeId}><div data-v-24a359e4${_scopeId}><h3 class="text-lg font-semibold mb-2" data-v-24a359e4${_scopeId}>🤖 AI-Powered Sentiment Analysis</h3><p class="text-green-100" data-v-24a359e4${_scopeId}>Advanced natural language processing analyzes millions of social media posts, news articles, and forum discussions to provide real-time market sentiment insights.</p></div><button class="px-6 py-3 bg-white bg-opacity-20 rounded-lg font-medium hover:bg-opacity-30 transition-colors" data-v-24a359e4${_scopeId}> API Access </button></div></div>`);
          } else {
            return [
              createVNode("div", { class: "mb-8" }, [
                createVNode("h1", { class: "text-3xl font-bold text-gray-900 mb-2" }, "Sentiment Analysis"),
                createVNode("p", { class: "text-gray-600" }, "Real-time market sentiment tracking across social media and news platforms")
              ]),
              createVNode("div", { class: "grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" }, [
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Overall Sentiment"),
                      createVNode("p", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(Math.round(overallSentiment.value * 100)) + "%", 1),
                      createVNode("div", { class: "flex items-center mt-2" }, [
                        createVNode("span", { class: "text-green-600 text-sm font-medium" }, "+5.2%"),
                        createVNode("span", { class: "text-xs text-gray-500 ml-1" }, "vs yesterday")
                      ])
                    ]),
                    createVNode("div", { class: "text-3xl" }, "📈")
                  ])
                ]),
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Total Mentions"),
                      createVNode("p", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(totalMentions.value.toLocaleString()), 1),
                      createVNode("div", { class: "flex items-center mt-2" }, [
                        createVNode("span", { class: "text-green-600 text-sm font-medium" }, "+12.5%"),
                        createVNode("span", { class: "text-xs text-gray-500 ml-1" }, "vs yesterday")
                      ])
                    ]),
                    createVNode("div", { class: "text-3xl" }, "💬")
                  ])
                ]),
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Positive Sentiment"),
                      createVNode("p", { class: "text-2xl font-bold text-green-600" }, toDisplayString(Math.round(protocolSentiments.value.filter((p) => p.current >= 0.7).length / protocolSentiments.value.length * 100)) + "%", 1),
                      createVNode("div", { class: "flex items-center mt-2" }, [
                        createVNode("span", { class: "text-green-600 text-sm font-medium" }, "+8.1%"),
                        createVNode("span", { class: "text-xs text-gray-500 ml-1" }, "vs yesterday")
                      ])
                    ]),
                    createVNode("div", { class: "text-3xl" }, "😊")
                  ])
                ]),
                createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "flex items-center justify-between" }, [
                    createVNode("div", null, [
                      createVNode("p", { class: "text-sm text-gray-600 mb-1" }, "Active Sources"),
                      createVNode("p", { class: "text-2xl font-bold text-gray-900" }, "12"),
                      createVNode("div", { class: "flex items-center mt-2" }, [
                        createVNode("span", { class: "text-green-600 text-sm font-medium" }, "+2"),
                        createVNode("span", { class: "text-xs text-gray-500 ml-1" }, "new sources")
                      ])
                    ]),
                    createVNode("div", { class: "text-3xl" }, "🔍")
                  ])
                ])
              ]),
              createVNode("div", { class: "bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8" }, [
                createVNode("div", { class: "flex flex-wrap items-center gap-4" }, [
                  createVNode("div", null, [
                    createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, "Timeframe"),
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => selectedTimeframe.value = $event,
                      class: "px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    }, [
                      (openBlock(), createBlock(Fragment, null, renderList(timeframes, (timeframe) => {
                        return createVNode("option", {
                          key: timeframe.value,
                          value: timeframe.value
                        }, toDisplayString(timeframe.label), 9, ["value"]);
                      }), 64))
                    ], 8, ["onUpdate:modelValue"]), [
                      [vModelSelect, selectedTimeframe.value]
                    ])
                  ]),
                  createVNode("div", { class: "flex-1" }),
                  createVNode("div", { class: "flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-2 rounded-full bg-green-50 px-3 py-1" }, [
                      createVNode("div", { class: "h-2 w-2 rounded-full bg-green-500 animate-pulse" }),
                      createVNode("span", { class: "text-sm font-medium text-green-700" }, "Live Updates")
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-3 gap-8" }, [
                createVNode("div", { class: "lg:col-span-2 space-y-6" }, [
                  createVNode("div", { class: "bg-white rounded-lg shadow-sm border border-gray-200" }, [
                    createVNode("div", { class: "px-6 py-4 border-b border-gray-200" }, [
                      createVNode("h2", { class: "text-lg font-semibold text-gray-900" }, "Protocol Sentiment")
                    ]),
                    createVNode("div", { class: "p-6" }, [
                      createVNode("div", { class: "space-y-6" }, [
                        (openBlock(true), createBlock(Fragment, null, renderList(protocolSentiments.value, (protocol) => {
                          return openBlock(), createBlock("div", {
                            key: protocol.protocol,
                            class: "p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                          }, [
                            createVNode("div", { class: "flex items-center justify-between mb-4" }, [
                              createVNode("div", { class: "flex items-center space-x-3" }, [
                                createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, toDisplayString(protocol.protocol), 1),
                                createVNode("span", {
                                  class: [getSentimentColor(protocol.current), "px-3 py-1 text-xs font-medium rounded-full"]
                                }, toDisplayString(getSentimentLabel(protocol.current)), 3)
                              ]),
                              createVNode("div", { class: "text-right" }, [
                                createVNode("div", { class: "text-2xl font-bold text-gray-900" }, toDisplayString(Math.round(protocol.current * 100)) + "%", 1),
                                createVNode("div", { class: "flex items-center text-sm" }, [
                                  createVNode("span", {
                                    class: protocol.change >= 0 ? "text-green-600" : "text-red-600"
                                  }, toDisplayString(protocol.change >= 0 ? "+" : "") + toDisplayString(Math.round(protocol.change * 100)) + "% ", 3),
                                  createVNode("span", { class: "text-gray-500 ml-1" }, toDisplayString(selectedTimeframe.value), 1)
                                ])
                              ])
                            ]),
                            createVNode("div", { class: "mb-4" }, [
                              createVNode("div", { class: "flex items-center justify-between text-sm text-gray-600 mb-2" }, [
                                createVNode("span", null, "Sentiment Breakdown"),
                                createVNode("span", null, toDisplayString(protocol.volume) + " mentions", 1)
                              ]),
                              createVNode("div", { class: "flex w-full bg-gray-200 rounded-full h-3" }, [
                                createVNode("div", {
                                  class: "bg-green-500 h-3 rounded-l-full transition-all duration-300",
                                  style: { width: protocol.details.positive + "%" }
                                }, null, 4),
                                createVNode("div", {
                                  class: "bg-yellow-500 h-3 transition-all duration-300",
                                  style: { width: protocol.details.neutral + "%" }
                                }, null, 4),
                                createVNode("div", {
                                  class: "bg-red-500 h-3 rounded-r-full transition-all duration-300",
                                  style: { width: protocol.details.negative + "%" }
                                }, null, 4)
                              ]),
                              createVNode("div", { class: "flex justify-between text-xs text-gray-500 mt-1" }, [
                                createVNode("span", null, toDisplayString(protocol.details.positive) + "% Positive", 1),
                                createVNode("span", null, toDisplayString(protocol.details.neutral) + "% Neutral", 1),
                                createVNode("span", null, toDisplayString(protocol.details.negative) + "% Negative", 1)
                              ])
                            ]),
                            createVNode("div", { class: "flex items-center justify-between text-sm text-gray-600" }, [
                              createVNode("div", { class: "flex items-center space-x-2" }, [
                                createVNode("span", null, "Sources:"),
                                createVNode("div", { class: "flex space-x-1" }, [
                                  (openBlock(true), createBlock(Fragment, null, renderList(protocol.sources, (source) => {
                                    return openBlock(), createBlock("span", {
                                      key: source,
                                      class: "px-2 py-1 bg-gray-200 rounded text-xs"
                                    }, toDisplayString(getPlatformIcon(source)) + " " + toDisplayString(source), 1);
                                  }), 128))
                                ])
                              ]),
                              createVNode("span", null, "Updated " + toDisplayString(protocol.lastUpdated), 1)
                            ])
                          ]);
                        }), 128))
                      ])
                    ])
                  ])
                ]),
                createVNode("div", { class: "space-y-6" }, [
                  createVNode("div", { class: "bg-white rounded-lg shadow-sm border border-gray-200" }, [
                    createVNode("div", { class: "px-6 py-4 border-b border-gray-200" }, [
                      createVNode("h2", { class: "text-lg font-semibold text-gray-900" }, "Trending Topics")
                    ]),
                    createVNode("div", { class: "p-6" }, [
                      createVNode("div", { class: "space-y-4" }, [
                        (openBlock(true), createBlock(Fragment, null, renderList(trendingTopics.value, (topic) => {
                          return openBlock(), createBlock("div", {
                            key: topic.topic,
                            class: "flex items-center justify-between p-3 bg-gray-50 rounded-md"
                          }, [
                            createVNode("div", null, [
                              createVNode("div", { class: "font-medium text-gray-900" }, toDisplayString(topic.topic), 1),
                              createVNode("div", { class: "text-sm text-gray-600" }, toDisplayString(topic.mentions) + " mentions", 1)
                            ]),
                            createVNode("div", { class: "text-right" }, [
                              createVNode("div", {
                                class: [getSentimentColor(topic.sentiment), "px-2 py-1 text-xs font-medium rounded-full mb-1"]
                              }, toDisplayString(Math.round(topic.sentiment * 100)) + "% ", 3),
                              createVNode("div", {
                                class: [topic.change.startsWith("+") ? "text-green-600" : "text-red-600", "text-xs font-medium"]
                              }, toDisplayString(topic.change), 3)
                            ])
                          ]);
                        }), 128))
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "bg-white rounded-lg shadow-sm border border-gray-200" }, [
                    createVNode("div", { class: "px-6 py-4 border-b border-gray-200" }, [
                      createVNode("h2", { class: "text-lg font-semibold text-gray-900" }, "Recent Mentions")
                    ]),
                    createVNode("div", { class: "p-6" }, [
                      createVNode("div", { class: "space-y-4 max-h-96 overflow-y-auto" }, [
                        (openBlock(true), createBlock(Fragment, null, renderList(recentMentions.value, (mention) => {
                          return openBlock(), createBlock("div", {
                            key: mention.id,
                            class: "p-3 bg-gray-50 rounded-md border border-gray-100"
                          }, [
                            createVNode("div", { class: "flex items-start justify-between mb-2" }, [
                              createVNode("div", { class: "flex items-center space-x-2" }, [
                                createVNode("span", null, toDisplayString(getPlatformIcon(mention.platform)), 1),
                                createVNode("span", { class: "font-medium text-sm text-gray-900" }, toDisplayString(mention.author), 1),
                                createVNode("span", { class: "text-xs text-gray-500" }, toDisplayString(mention.protocol), 1)
                              ]),
                              createVNode("span", {
                                class: [getSentimentColor(mention.sentiment), "px-2 py-1 text-xs font-medium rounded-full"]
                              }, toDisplayString(Math.round(mention.sentiment * 100)) + "% ", 3)
                            ]),
                            createVNode("p", { class: "text-sm text-gray-700 mb-2 line-clamp-3" }, toDisplayString(mention.content), 1),
                            createVNode("div", { class: "flex items-center justify-between text-xs text-gray-500" }, [
                              createVNode("span", null, toDisplayString(mention.timestamp), 1),
                              createVNode("div", { class: "flex space-x-2" }, [
                                createVNode("span", null, "👍 " + toDisplayString(mention.engagement.likes), 1),
                                mention.engagement.retweets > 0 ? (openBlock(), createBlock("span", { key: 0 }, "🔄 " + toDisplayString(mention.engagement.retweets), 1)) : createCommentVNode("", true),
                                createVNode("span", null, "💬 " + toDisplayString(mention.engagement.replies), 1)
                              ])
                            ])
                          ]);
                        }), 128))
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "mt-8 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg p-6 text-white" }, [
                createVNode("div", { class: "flex items-center justify-between" }, [
                  createVNode("div", null, [
                    createVNode("h3", { class: "text-lg font-semibold mb-2" }, "🤖 AI-Powered Sentiment Analysis"),
                    createVNode("p", { class: "text-green-100" }, "Advanced natural language processing analyzes millions of social media posts, news articles, and forum discussions to provide real-time market sentiment insights.")
                  ]),
                  createVNode("button", { class: "px-6 py-3 bg-white bg-opacity-20 rounded-lg font-medium hover:bg-opacity-30 transition-colors" }, " API Access ")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Sentiment.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Sentiment = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-24a359e4"]]);
export {
  Sentiment as default
};
