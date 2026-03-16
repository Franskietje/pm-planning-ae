const COOKIE_NAME = 'fm_session';

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (request.method === 'OPTIONS') {
      return new Response(null, { status: 204, headers: corsHeaders(request, env) });
    }

    try {
      if (url.pathname === '/ping' && request.method === 'GET') {
        return json({ ok: true, message: 'API is running' }, 200, request, env);
      }

      if (url.pathname === '/auth/login' && request.method === 'POST') {
        return await handleLogin(request, env);
      }

      if (url.pathname === '/auth/logout' && request.method === 'POST') {
        return handleLogout(request, env);
      }

      if (url.pathname === '/find/planning' && request.method === 'GET') {
        return await handleFindPlanning(request, env);
      }

      return json({ ok: false, error: 'Not found' }, 404, request, env);
    } catch (err) {
      return json({ ok: false, error: String(err.message || err) }, 500, request, env);
    }
  }
};

function corsHeaders(request, env) {
  const origin = request.headers.get('Origin') || '';
  const allowedOrigins = (env.ALLOWED_ORIGINS || '')
    .split(',')
    .map(v => v.trim())
    .filter(Boolean);

  const allowOrigin = allowedOrigins.includes(origin) ? origin : (allowedOrigins[0] || '*');

  return {
    'Access-Control-Allow-Origin': allowOrigin,
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Vary': 'Origin'
  };
}

function json(data, status, request, env, extraHeaders = {}) {
  return new Response(JSON.stringify(data, null, 2), {
    status,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      ...corsHeaders(request, env),
      ...extraHeaders
    }
  });
}

function firstName(name) {
  if (!name) return null;
  const parts = String(name).trim().split(/\s+/);
  return parts[parts.length - 1] || null;
}

function toMdy(isoDate) {
  const [y, m, d] = isoDate.split('-');
  return `${m}/${d}/${y}`;
}

function parseCookies(request) {
  const raw = request.headers.get('Cookie') || '';
  const cookies = {};

  raw.split(';').forEach(part => {
    const i = part.indexOf('=');
    if (i > 0) {
      const k = part.slice(0, i).trim();
      const v = part.slice(i + 1).trim();
      cookies[k] = decodeURIComponent(v);
    }
  });

  return cookies;
}

function buildCookie(value, maxAgeSeconds) {
  return [
    `${COOKIE_NAME}=${encodeURIComponent(value)}`,
    'Path=/',
    'HttpOnly',
    'Secure',
    'SameSite=None',
    `Max-Age=${maxAgeSeconds}`
  ].join('; ');
}

function clearCookie() {
  return [
    `${COOKIE_NAME}=`,
    'Path=/',
    'HttpOnly',
    'Secure',
    'SameSite=None',
    'Max-Age=0'
  ].join('; ');
}

async function fmRequest(env, path, method = 'GET', headers = {}, body = null) {
  const host = String(env.FM_HOST || '').replace(/\/$/, '');
  const version = env.FM_VERSION || 'vLatest';
  const db = encodeURIComponent(env.FM_DB || '');
  const url = `${host}/fmi/data/${version}/databases/${db}${path}`;

  const init = {
    method,
    headers,
    body
  };

  const res = await fetch(url, init);
  const text = await res.text();

  let parsed;
  try {
    parsed = JSON.parse(text);
  } catch {
    throw new Error(`FileMaker non-JSON response (${res.status}): ${text}`);
  }

  if (!res.ok) {
    throw new Error(JSON.stringify(parsed));
  }

  return parsed;
}

async function fmLogin(env, username, password) {
  const auth = btoa(`${username}:${password}`);

  const resp = await fmRequest(
    env,
    '/sessions',
    'POST',
    {
      'Content-Type': 'application/json',
      'Authorization': `Basic ${auth}`
    },
    '{}'
  );

  const token = resp?.response?.token;
  if (!token) throw new Error('Login failed');
  return token;
}

async function fmFind(env, token, layout, query, sort = [], limit = 5000) {
  const payload = {
    query: Array.isArray(query) ? query : [query],
    limit
  };

  if (sort && sort.length) payload.sort = sort;

  const resp = await fmRequest(
    env,
    `/layouts/${encodeURIComponent(layout)}/_find`,
    'POST',
    {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    JSON.stringify(payload)
  );

  return resp?.response?.data || [];
}

async function handleLogin(request, env) {
  const contentType = request.headers.get('content-type') || '';

  let username = '';
  let password = '';

  if (contentType.includes('application/json')) {
    const body = await request.json();
    username = body?.username || '';
    password = body?.password || '';
  } else {
    const form = await request.formData();
    username = String(form.get('username') || '');
    password = String(form.get('password') || '');
  }

  if (!username || !password) {
    return json({ ok: false, error: 'Missing username or password' }, 400, request, env);
  }

  try {
    const token = await fmLogin(env, username, password);

    return json(
      { ok: true, user: username },
      200,
      request,
      env,
      { 'Set-Cookie': buildCookie(token, 60 * 60 * 8) }
    );
  } catch {
    return json({ ok: false, error: 'Login failed' }, 401, request, env);
  }
}

function handleLogout(request, env) {
  return json({ ok: true }, 200, request, env, { 'Set-Cookie': clearCookie() });
}

async function handleFindPlanning(request, env) {
  const url = new URL(request.url);

  const fromISO = url.searchParams.get('from');
  const toISO = url.searchParams.get('to');

  if (!fromISO || !toISO) {
    return json({ ok: false, error: 'Missing from/to parameters' }, 400, request, env);
  }

  const cookies = parseCookies(request);
  const token = cookies[COOKIE_NAME];

  if (!token) {
    return json({ ok: false, error: 'Not logged in' }, 401, request, env);
  }

  const from = toMdy(fromISO);
  const to = toMdy(toISO);

  const query = [
    {
      datum_van: `${from}..${to}`,
      flag_hou_rekening_voor_planning: 1
    },
    {
      datum_tot: `${from}..${to}`,
      flag_hou_rekening_voor_planning: 1
    },
    {
      datum_van: `..${from}`,
      datum_tot: `${to}..`,
      flag_hou_rekening_voor_planning: 1
    }
  ];

  const sort = [
    { fieldName: '_k2_dossier_ID', sortOrder: 'ascend' },
    { fieldName: 'datum_van', sortOrder: 'ascend' }
  ];

  try {
    const records = await fmFind(env, token, '_dossiersdata', query, sort, 5000);

    const planning = {};
    const dossierFirstDate = {};

    for (const r of records) {
      const fd = r?.fieldData || {};
      const dossierId = fd['_k2_dossier_ID'];
      if (!dossierId) continue;

      const van = fd['datum_van'];
      const tot = fd['datum_tot'];
      if (!van || !tot) continue;

      if (!planning[dossierId]) planning[dossierId] = [];
      planning[dossierId].push({
        type: fd['type'] || null,
        datum_van: van,
        datum_tot: tot
      });

      const eventStart = Math.max(Date.parse(van), Date.parse(from));
      if (!dossierFirstDate[dossierId] || eventStart < dossierFirstDate[dossierId]) {
        dossierFirstDate[dossierId] = eventStart;
      }
    }

    const orderedPlanning = Object.fromEntries(
      Object.entries(planning).sort(([a], [b]) => {
        const da = dossierFirstDate[a] || Number.MAX_SAFE_INTEGER;
        const db = dossierFirstDate[b] || Number.MAX_SAFE_INTEGER;
        return da - db;
      })
    );

    const dossierIds = Object.keys(orderedPlanning);
    const dossierInfo = {};

    if (dossierIds.length) {
      const dossierQuery = dossierIds.map(id => ({ _k1_dossier_ID: String(id) }));
      const dossierRecords = await fmFind(env, token, 'Dossiers_form_detail', dossierQuery, [], 5000);

      for (const r of dossierRecords) {
        const fd = r?.fieldData || {};
        const id = fd['_k1_dossier_ID'];
        if (!id) continue;

        const status = Number(fd['dossiers_dossierStatussen::volg'] || 0);
        if (status > 6) {
          delete orderedPlanning[id];
          continue;
        }

        const full1 = fd['projectleider1_ae'] || null;
        const full2 = fd['projectleider2_ae'] || null;

        const first1 = firstName(full1);
        const first2 = firstName(full2);

        const projectleidersFull = [full1, full2].filter(Boolean);
        const projectleidersFirst = [first1, first2].filter(Boolean);

        dossierInfo[id] = {
          dossiernaam: fd['dossiernaam'] || null,
          status: fd['dossiers_dossierStatussen::volg'] || null,
          projectleiders: projectleidersFull,
          projectleiders_first: projectleidersFirst,
          projectleiders_text: projectleidersFirst.join(', ')
        };
      }
    }

    return json(
      {
        ok: true,
        month: '2026-01',
        count: Object.keys(orderedPlanning).length,
        planning: orderedPlanning,
        dossiers: dossierInfo
      },
      200,
      request,
      env
    );
  } catch (err) {
    return json({ ok: false, error: String(err.message || err) }, 500, request, env);
  }
}
