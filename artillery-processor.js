export {
  setAuthToken,
  generateRandomData,
  logResponse,
  validateSentimentResponse
};

function setAuthToken(requestParams, response, context, ee, next) {
  if (response.body && typeof response.body === 'string') {
    try {
      const data = JSON.parse(response.body);
      if (data.token) {
        context.vars.auth_token = data.token;
      }
    } catch (e) {
      console.log('Failed to parse auth response');
    }
  }
  return next();
}

function generateRandomData(context, events, done) {
  const symbols = ['BTC', 'ETH', 'ADA', 'SOL', 'DOT', 'LINK', 'UNI', 'AAVE'];
  const addresses = [
    '0x742d35Cc60C0b69C9B0F11B4E32D1E4ad5c32',
    '0x7D1AfA7B718fb893dB30A3aBc0Cfc608AaCfeBB0',
    '0x8ba1f109551bD432803012645Hac136c23b1234'
  ];
  const keywords = [
    'bullish sentiment', 
    'market analysis', 
    'price prediction',
    'technical analysis',
    'market trend',
    'trading signal'
  ];

  context.vars.symbol = symbols[Math.floor(Math.random() * symbols.length)];
  context.vars.address = addresses[Math.floor(Math.random() * addresses.length)];
  context.vars.keyword = keywords[Math.floor(Math.random() * keywords.length)];
  
  return done();
}

function logResponse(requestParams, response, context, ee, next) {
  if (response.statusCode >= 400) {
    console.log(`Error ${response.statusCode} for ${requestParams.url}`);
    if (response.body) {
      console.log(`Response: ${response.body.substring(0, 200)}`);
    }
  }
  
  ee.emit('counter', 'status_' + response.statusCode, 1);
  
  return next();
}

function validateSentimentResponse(requestParams, response, context, ee, next) {
  if (response.body && typeof response.body === 'string') {
    try {
      const data = JSON.parse(response.body);
      
      if (data.sentiment_score !== undefined) {
        ee.emit('counter', 'valid_sentiment_response', 1);
      } else {
        ee.emit('counter', 'invalid_sentiment_response', 1);
      }
      
      if (data.processing_time) {
        ee.emit('histogram', 'sentiment_processing_time', data.processing_time);
      }
      
    } catch (e) {
      ee.emit('counter', 'json_parse_error', 1);
    }
  }
  
  return next();
}

