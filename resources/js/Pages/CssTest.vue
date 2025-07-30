<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { onMounted } from 'vue';

onMounted(() => {
  // Check if CSS is loading properly
  console.log('🎨 CSS Test page mounted');
  
  // Add CSS loading test
  const testElement = document.createElement('div');
  testElement.className = 'css-loading-test';
  testElement.style.cssText = 'position: fixed; top: 10px; right: 10px; background: green; color: white; padding: 5px; z-index: 9999; font-size: 12px;';
  testElement.textContent = 'CSS Loaded ✅';
  document.body.appendChild(testElement);
  
  // Check Tailwind classes
  const hasBackgroundGray = getComputedStyle(document.body).backgroundColor;
  console.log('📊 Body background color:', hasBackgroundGray);
  
  setTimeout(() => {
    document.body.removeChild(testElement);
  }, 3000);
});
</script>

<template>
  <AppLayout>
    <!-- CSS Loading Test Indicator -->
    <div class="css-loading-test fixed top-4 left-4 bg-green-500 text-white p-2 rounded shadow-lg z-50">
      <span class="text-sm font-medium">✅ CSS is loading properly!</span>
    </div>
    
    <div class="space-y-8">
      <!-- CSS Test Header -->
      <div class="text-center">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">CSS Layout Test</h1>
        <p class="text-lg text-gray-600">Testing Tailwind CSS classes and layout components</p>
      </div>

      <!-- Grid Test -->
      <div class="card">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Grid Layout Test</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="bg-blue-100 p-4 rounded-lg">
            <h3 class="font-semibold text-blue-900">Grid Item 1</h3>
            <p class="text-blue-700">This should be in a responsive grid</p>
          </div>
          <div class="bg-green-100 p-4 rounded-lg">
            <h3 class="font-semibold text-green-900">Grid Item 2</h3>
            <p class="text-green-700">Grid should adapt to screen size</p>
          </div>
          <div class="bg-purple-100 p-4 rounded-lg">
            <h3 class="font-semibold text-purple-900">Grid Item 3</h3>
            <p class="text-purple-700">Colors should be working</p>
          </div>
        </div>
      </div>

      <!-- Flex Test -->
      <div class="card">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Flexbox Layout Test</h2>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg">
          <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-indigo-500 rounded-full flex items-center justify-center">
              <span class="text-white font-bold">A</span>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900">Flex Item</h3>
              <p class="text-sm text-gray-600">Should be aligned properly</p>
            </div>
          </div>
          <button class="btn btn-primary">Action Button</button>
        </div>
      </div>

      <!-- Z-Index and Positioning Test -->
      <div class="card relative">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Z-Index and Positioning Test</h2>
        <div class="relative h-32 bg-gray-100 rounded-lg overflow-hidden">
          <div class="absolute inset-0 bg-blue-200 opacity-50"></div>
          <div class="absolute top-4 left-4 z-10 bg-white p-3 rounded shadow-lg">
            <p class="text-sm font-medium">This should be on top</p>
          </div>
          <div class="absolute bottom-4 right-4 bg-red-500 text-white p-2 rounded">
            <p class="text-xs">Bottom right</p>
          </div>
        </div>
      </div>

      <!-- Typography Test -->
      <div class="card">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Typography Test</h2>
        <div class="space-y-4">
          <h1 class="text-4xl font-bold text-gray-900">Heading 1</h1>
          <h2 class="text-3xl font-semibold text-gray-800">Heading 2</h2>
          <h3 class="text-2xl font-medium text-gray-700">Heading 3</h3>
          <p class="text-base text-gray-600 leading-relaxed">
            This is a paragraph with normal text. It should have proper line height and spacing.
            The font should be readable and well-styled. This text should wrap properly and
            maintain good readability across different screen sizes.
          </p>
          <p class="text-sm text-gray-500">Small text for captions or metadata</p>
        </div>
      </div>

      <!-- Color Palette Test -->
      <div class="card">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Color Palette Test</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
          <div class="bg-red-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Red</span>
          </div>
          <div class="bg-blue-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Blue</span>
          </div>
          <div class="bg-green-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Green</span>
          </div>
          <div class="bg-yellow-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Yellow</span>
          </div>
          <div class="bg-purple-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Purple</span>
          </div>
          <div class="bg-pink-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Pink</span>
          </div>
          <div class="bg-indigo-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Indigo</span>
          </div>
          <div class="bg-gray-500 h-16 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">Gray</span>
          </div>
        </div>
      </div>

      <!-- Mobile Responsiveness Test -->
      <div class="card">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Responsive Design Test</h2>
        <div class="space-y-4">
          <div class="block sm:hidden bg-red-100 p-4 rounded">
            <p class="text-red-800 font-medium">Visible only on mobile (sm and below)</p>
          </div>
          <div class="hidden sm:block md:hidden bg-yellow-100 p-4 rounded">
            <p class="text-yellow-800 font-medium">Visible only on small tablets (sm to md)</p>
          </div>
          <div class="hidden md:block lg:hidden bg-blue-100 p-4 rounded">
            <p class="text-blue-800 font-medium">Visible only on tablets (md to lg)</p>
          </div>
          <div class="hidden lg:block bg-green-100 p-4 rounded">
            <p class="text-green-800 font-medium">Visible only on desktop (lg and above)</p>
          </div>
        </div>
      </div>

      <!-- Debug Information -->
      <div class="card bg-gray-50">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Debug Information</h2>
        <div class="text-sm space-y-2 font-mono">
          <p><strong>Screen Size:</strong> Check the responsive boxes above</p>
          <p><strong>CSS Status:</strong> If you can see colors and proper spacing, Tailwind is working!</p>
          <p><strong>Layout Status:</strong> Check that the sidebar doesn't overlap this content</p>
          <p><strong>Z-Index:</strong> The white box in the positioning test should be on top</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>