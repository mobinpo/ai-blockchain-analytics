// Temporary debugging styles to identify layout issues
export const addDebugStyles = () => {
  if (process.env.NODE_ENV === 'development') {
    const style = document.createElement('style');
    style.textContent = `
      /* Debug borders for layout debugging */
      .debug-layout * {
        border: 1px solid rgba(255, 0, 0, 0.2) !important;
      }
      
      .debug-layout .flex {
        border-color: rgba(0, 255, 0, 0.5) !important;
      }
      
      .debug-layout .grid {
        border-color: rgba(0, 0, 255, 0.5) !important;
      }
      
      /* Highlight potential overflow issues */
      .debug-overflow {
        outline: 2px solid orange !important;
      }
      
      /* Show z-index stacking */
      .debug-z-index [class*="z-"] {
        background-color: rgba(255, 255, 0, 0.1) !important;
      }
    `;
    document.head.appendChild(style);
    
    console.log('🐛 Debug styles loaded. Add "debug-layout" class to elements for visual debugging');
  }
};

export const removeDebugStyles = () => {
  const debugStyles = document.querySelector('style[data-debug="true"]');
  if (debugStyles) {
    debugStyles.remove();
    console.log('🧹 Debug styles removed');
  }
};