module.exports = async (req, res) => {
  const makeWebhook = process.env.MAKE_WEBHOOK || 'https://hook.eu2.make.com/rqwaw3g4ldz8vpxp3rl669q9fklv5534';

  // Basic anti-spam settings
  const RATE_LIMIT_WINDOW_MS = 10 * 60 * 1000; // 10 min
  const RATE_LIMIT_MAX = 3; // max 3 submissions per IP per window

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return res.status(405).send('Method Not Allowed');
  }

  const body = req.body || {};
  const lang = (body.lang || 'pt').toString().toLowerCase() === 'en' ? 'en' : 'pt';
  const thankYou = lang === 'en' ? '/en/thankyou.html' : '/obrigado.html';

  // Honeypot: bots often fill hidden fields
  if ((body.website || '').toString().trim() !== '') {
    return res.redirect(303, `${thankYou}?status=error&reason=spam`);
  }

  const forwardedFor = (req.headers['x-forwarded-for'] || '').toString();
  const ip = (forwardedFor.split(',')[0] || req.socket?.remoteAddress || '').toString().trim();

  // In-memory IP limiter (basic protection)
  const now = Date.now();
  globalThis.__leadRateLimit = globalThis.__leadRateLimit || new Map();
  const bucket = globalThis.__leadRateLimit;

  const recent = (bucket.get(ip) || []).filter((ts) => now - ts < RATE_LIMIT_WINDOW_MS);
  if (recent.length >= RATE_LIMIT_MAX) {
    bucket.set(ip, recent);
    return res.redirect(303, `${thankYou}?status=error&reason=rate_limited`);
  }
  recent.push(now);
  bucket.set(ip, recent);

  try {
    const payload = {
      lang,
      firstName: (body.firstName || '').toString().trim(),
      lastName: (body.lastName || '').toString().trim(),
      email: (body.email || '').toString().trim(),
      phone: (body.phone || '').toString().trim(),
      setor: (body.setor || '').toString().trim(),
      service: (body.service || '').toString().trim(),
      message: (body.message || '').toString().trim(),
      sourceUrl: (req.headers.referer || '').toString(),
      submittedAt: new Date().toISOString(),
      ip,
      userAgent: (req.headers['user-agent'] || '').toString(),
    };

    const response = await fetch(makeWebhook, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const status = response.ok ? 'ok' : 'error';
    return res.redirect(303, `${thankYou}?status=${encodeURIComponent(status)}`);
  } catch (error) {
    return res.redirect(303, `${thankYou}?status=error`);
  }
};
