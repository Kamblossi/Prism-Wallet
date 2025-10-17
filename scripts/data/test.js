const https = require('https');
https.get('https://openexchangerates.org/api/currencies.json', res => {
  let data = '';
  res.on('data', chunk => data += chunk);
  res.on('end', () => {
    const obj = JSON.parse(data);
    const entries = Object.entries(obj).sort(([a],[b]) => a.localeCompare(b));
    const list = entries.map(([code, name]) => ({
      code,
      name: name.normalize('NFKD').replace(/[\\u0300-\\u036f]/g, '').replace(/[^\\x00-\\x7F]/g, '')
    }));
    list.slice(0,5).forEach(item => console.log(item));
  });
});
