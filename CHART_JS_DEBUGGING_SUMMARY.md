# 🔧 Chart.js Maximum Call Stack Issue - Debugging Summary

## 🚨 **Persistent Issue:**
```
RangeError: Maximum call stack size exceeded at addScopes (chunk-AE434WQH.js)
```

## 🔍 **Debugging Steps Applied:**

### ✅ **Fixes Already Applied:**

1. **Data Cloning:**
   ```javascript
   // Before: Direct assignment (reactive)
   this.chartData = combinedData
   
   // After: Deep clone (non-reactive)
   this.chartData = JSON.parse(JSON.stringify(combinedData))
   ```

2. **Type Conversion:**
   ```javascript
   // Explicit type conversion to prevent Chart.js issues
   priceData.push({ 
       x: new Date(point.timestamp), 
       y: Number(point.price) 
   })
   ```

3. **Spread Operators:**
   ```javascript
   // Create new arrays to avoid references
   data: [...priceData]  // Instead of data: priceData
   ```

4. **Chart Instance Protection:**
   ```javascript
   // Prevent chart instance from becoming reactive (Vue 3)
   this.chart = markRaw(chartInstance)
   ```

5. **Defensive Error Handling:**
   ```javascript
   try {
       this.chart.update('none')
   } catch (error) {
       console.warn('Chart update failed:', error)
       this.initializeChart() // Reinitialize on failure
   }
   ```

### 🔧 **Latest Test: Simplified Configuration**

Since the issue persists, we've simplified the chart configuration:

1. **Removed Time Scale:** Changed `type: 'time'` to `type: 'linear'`
2. **Simplified Data:** Using array indices instead of Date objects
3. **Isolated Options:** Moved chart options to separate method

```javascript
// Test Configuration
scales: {
    x: {
        type: 'linear', // Instead of 'time'
        display: true,
    }
}

// Test Data Format
priceData.push({ x: i, y: price }) // Instead of { x: Date, y: price }
```

## 🧪 **Hypothesis:**
The maximum call stack error in Chart.js's `addScopes` function suggests one of:

1. **Time Adapter Issue:** Chart.js time parsing creating circular references
2. **Vue Reactivity Leak:** Despite our fixes, Vue's proxy system is still interfering
3. **Chart.js Version Conflict:** Version compatibility issue with Vue/Vite
4. **Data Structure Issue:** Specific data format causing Chart.js internal loops

## 🎯 **Current Testing Strategy:**

### **Phase 1: Isolate Time Scale Issue**
- ✅ Temporarily disabled time scale (`type: 'linear'`)
- ✅ Removed Date objects from data
- ✅ Using simple numeric indices

### **Phase 2: Component Structure Test** (Next)
If simplified version works:
- Problem is in time handling/date parsing
- Need to investigate Chart.js time adapter
- Consider alternative time libraries

If simplified version fails:
- Problem is fundamental Vue/Chart.js interaction
- Consider chart library alternatives
- Implement fallback chart solution

## 🚨 **Critical Findings:**

### **Error Pattern:**
The error consistently occurs in Chart.js internal functions:
```
at addScopes (chunk-AE434WQH.js:2131:19)
at addScopesFromKey (chunk-AE434WQH.js:2171:11)  
at createSubResolver (chunk-AE434WQH.js:2155:13)
```

This suggests Chart.js is getting stuck in its internal scope resolution system, likely due to:
- Circular object references
- Reactive proxy interference  
- Invalid configuration objects
- Time parsing infinite loops

### **Location:**
Error occurs in `SentimentPriceTimeline.vue:310` which is the catch block in `loadData()`, indicating the error happens during:
- `processChartData()`
- `updateChart()` 
- `calculateStatistics()`

## 🛠️ **Next Steps:**

1. **Test Current Simplified Version:**
   - Check if linear scale resolves the issue
   - Confirm chart renders without errors

2. **If Successful:**
   - Gradually re-enable time features
   - Test different time adapters
   - Implement proper date handling

3. **If Still Failing:**
   - Consider alternative charting libraries (ApexCharts, D3.js)
   - Implement server-side chart generation
   - Create simplified HTML5 canvas charts

## 📊 **Fallback Strategy:**

If Chart.js continues to fail, implement a simple fallback:

```javascript
// Fallback: Simple SVG chart
<svg class="chart-fallback" width="800" height="400">
  <polyline :points="pricePoints" stroke="#3B82F6" fill="none"/>
  <polyline :points="sentimentPoints" stroke="#10B981" fill="none"/>
</svg>
```

This ensures the sentiment dashboard remains functional while we resolve the Chart.js integration issues.