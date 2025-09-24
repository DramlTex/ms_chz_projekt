<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'NkApi.php';

$itemsPerPage = 50;
$page   = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$fromRaw = trim($_GET['from'] ?? '');
$toRaw   = trim($_GET['to']   ?? '');

// convert YYYY-MM-DDTHH:ii to API format
$fmt = static function(string $v): string {
    if ($v === '') return '';
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) $v .= ':00';
    return $v;
};

$fromDate = $fmt($fromRaw);
$toDate   = $fmt($toRaw);
if ($toDate === '') $toDate = date('Y-m-d H:i:s');

$all = [];
if ($search !== '') {
    $offset = 0;
    $batch = 1000;
    $params = [];
    if ($fromDate !== '') $params['from_date'] = $fromDate;
    if ($toDate   !== '') $params['to_date']   = $toDate;
    do {
        $chunk = NkApi::list($params, $batch, $offset);
        $all = array_merge($all, $chunk);
        $offset += $batch;
    } while (count($chunk) === $batch);
    $all = array_filter($all, function ($row) use ($search) {
        $q = mb_strtolower($search);
        return mb_stripos(mb_strtolower($row['good_name'] ?? ''), $q) !== false
            || str_contains(strtolower(gtin($row)), strtolower($search));
    });
    $total = count($all);
    $pages = max(1, (int)ceil($total / $itemsPerPage));
    $page = min($page, $pages);
    $cards = array_slice($all, ($page - 1) * $itemsPerPage, $itemsPerPage);
    $hasMore = $page < $pages;
} else {
    $offset = 0;
    $batch = 1000;
    $params = [];
    if ($fromDate !== '') $params['from_date'] = $fromDate;
    if ($toDate   !== '') $params['to_date']   = $toDate;
    do {
        $chunk = NkApi::list($params, $batch, $offset);
        $all = array_merge($all, $chunk);
        $offset += $batch;
    } while (count($chunk) === $batch);
    $total = count($all);
    $pages = max(1, (int)ceil($total / $itemsPerPage));
    $page = min($page, $pages);
    $cards = array_slice($all, ($page - 1) * $itemsPerPage, $itemsPerPage);
    $hasMore = $page < $pages;
}

$ids   = array_column($cards, 'good_id');
$fulls = [];
foreach (array_chunk($ids, 25) as $chunk) {
    foreach (NkApi::feedProduct($chunk) as $g) {
        $fulls[$g['good_id']] = $g;
    }
}

function attr(array $card, string $name): string
{
    foreach ($card['good_attrs'] ?? [] as $a) {
        if (($a['attr_name'] ?? '') === $name) return $a['attr_value'] ?? '';
    }
    return '';
}

function gtin(array $row): string
{
    if (!empty($row['gtin'])) return $row['gtin'];
    foreach ($row['identified_by'] ?? [] as $id) {
        if (($id['type'] ?? '') === 'gtin') return $id['value'] ?? '';
    }
    return '';
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Карточки НК (детально)</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:2rem;background:linear-gradient(120deg,#eef2f3,#e1e8ed)}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #ddd;padding:.4rem .6rem;font-size:14px}
th{background:linear-gradient(#fafafa,#eaeaea);position:sticky;top:0;z-index:1}
button{background:linear-gradient(135deg,#4b86db,#4277d6);color:#fff;border:0;padding:.3rem .6rem;border-radius:4px}
.status-draft{color:#888}
.status-notsigned,.status-waitSign{color:#d88700}
.status-published{color:#1a7f14}
.filter{margin-bottom:1rem}
.filter input{padding:.4rem}
.pagination{text-align:center;margin-top:1rem}
.pagination a,.pagination span{margin:0 2px;padding:.2rem .5rem;border:1px solid #ddd;text-decoration:none}
.pagination span.current{font-weight:bold;background:#ddd}
.actions{display:flex;gap:.6rem;margin-top:1rem;flex-wrap:wrap}
.actions button{flex:0 0 auto}
tbody tr.needs-sign td{background:#fff7d6}
.sign-panel{margin-top:2rem;padding:1.5rem;border-radius:6px;background:rgba(255,255,255,.92);box-shadow:0 15px 35px rgba(0,0,0,.08)}
.sign-panel h3{margin-top:0;margin-bottom:.4rem}
.sign-panel p{margin:.2rem 0 .8rem;color:#4a4a4a}
.sign-panel .hint{font-size:.85rem;color:#666;margin-top:-.2rem}
.sign-panel .sign-controls{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.sign-panel select{flex:1 0 220px;padding:.5rem;border:1px solid #c5c5c5;border-radius:4px}
.sign-panel button{padding:.5rem 1rem}
#signLog{white-space:pre-wrap;border:1px solid #ccc;padding:1rem;margin-top:1rem;background:#f9f9f9;height:200px;overflow:auto;border-radius:4px;font-size:13px}
</style>
<script src="cadesplugin_api.js"></script>
</head>
<body>

<h2>Всего карточек: <?=isset($total)?$total:count($cards)?></h2>
<form method="get" class="filter">
  <input type="text" name="q" placeholder="Поиск" value="<?=htmlspecialchars($search)?>">
  <input type="datetime-local" name="from" value="<?=htmlspecialchars($fromRaw)?>">
  <input type="datetime-local" name="to"   value="<?=htmlspecialchars($toRaw)?>">
  <button type="submit">Найти</button>
</form>
<table>
<thead>
<tr>
  <th><input type="checkbox" id="selectAll"></th>
  <th>ID</th><th>GTIN</th><th>Наименование</th>
  <th>ТНВЭД‑10</th><th>Артикул</th><th>Цвет</th><th>Размер</th>
  <th>ТР ТС</th><th>Декларация</th>
  <th>Статус</th><th>Детализ.</th><th>Создать в МС</th>
</tr>
</thead>
<tbody>
<?php foreach ($cards as $row):
      $id   = $row['good_id'];
      $full = $fulls[$id] ?? [];

      $tn10  = attr($full, 'Код ТНВЭД');
      $art   = attr($full, 'Модель / артикул производителя');
      $color = attr($full, 'Цвет');
      $size  = attr($full, 'Размер одежды / изделия');
      $trts  = attr($full, 'Номер технического регламента');
      $decl  = attr($full, 'Декларация о соответствии');

      $stat = htmlspecialchars($row['good_status'] ?? '');
      $det  = implode(', ', $row['good_detailed_status'] ?? []);
      $cls  = [
          'draft'     =>'status-draft',
          'notsigned' =>'status-notsigned',
          'waitsign'  =>'status-waitSign',
          'published' =>'status-published'
      ][$stat] ?? '';
?>
<tr class="<?=$cls?>"
    data-id="<?=$id?>"
    data-gtin="<?=htmlspecialchars(gtin($row))?>"
    data-name="<?=htmlspecialchars($row['good_name'] ?? '')?>"
    data-tnved="<?=htmlspecialchars($tn10)?>"
    data-article="<?=htmlspecialchars($art)?>">
  <td><input type="checkbox" class="select-item"></td>
  <td><?=$id?></td>
  <td><?=htmlspecialchars(gtin($row))?></td>
  <td><?=htmlspecialchars($row['good_name'] ?? '')?></td>
  <td><?=$tn10?></td>
  <td><?=htmlspecialchars($art)?></td>
  <td><?=htmlspecialchars($color)?></td>
  <td><?=htmlspecialchars($size)?></td>
  <td><?=htmlspecialchars($trts)?></td>
  <td><?=htmlspecialchars($decl)?></td>
  <td><?=$stat?></td>
  <td><?=$det?></td>
  <td><button class="create-single">Создать</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="actions">
  <button type="button" id="createSelected">Создать выбранные</button>
</div>

<div id="modal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;align-items:center;justify-content:center;background:rgba(0,0,0,.4)">
  <div class="modal-content" style="background:#fff;padding:1rem;border-radius:4px;position:relative">
    <button class="close-modal" style="position:absolute;top:.2rem;right:.2rem;border:0;background:red;font-size:0.5rem;cursor:pointer">&times;</button>
    <div id="modal-body"></div>
  </div>
</div>

<section class="sign-panel" id="signPanel">
  <h3>Подписание карточек</h3>
  <p class="hint">Отметьте карточки в таблице или нажмите «Отметить ожидающие подписи», выберите сертификат и запустите подпись.</p>
  <div class="sign-controls">
    <select id="signCert">
      <option value="">Загрузка сертификатов…</option>
    </select>
    <button type="button" id="loadAwaiting">Отметить ожидающие подписи</button>
  </div>
  <button type="button" id="signSelectedBtn">Подписать выбранные</button>
  <div id="signLog">Инициализация CryptoPro…</div>
</section>

<?php if($page>1): ?>
  <a href="?<?=http_build_query(['page'=>$page-1,'q'=>$search,'from'=>$fromRaw,'to'=>$toRaw])?>">&lt; Предыдущая</a>
<?php endif; ?>
<?php if($hasMore): ?>
  <a style="float:right" href="?<?=http_build_query(['page'=>$page+1,'q'=>$search,'from'=>$fromRaw,'to'=>$toRaw])?>">Следующая &gt;</a>
<?php endif; ?>
<?php if($pages>1): ?>
  <div class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <?php if($i==$page): ?>
        <span class="current"><?=$i?></span>
      <?php else: ?>
        <a href="?<?=http_build_query(['page'=>$i,'q'=>$search,'from'=>$fromRaw,'to'=>$toRaw])?>"><?=$i?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<script>
(() => {
  const modal = document.getElementById('modal');
  const modalBody = modal.querySelector('#modal-body');
  modal.querySelector('.close-modal').addEventListener('click', hideModal);
  modal.addEventListener('click', e => { if (e.target === modal) hideModal(); });
  function showModal(html) { modalBody.innerHTML = html; modal.style.display = 'flex'; }
  function hideModal() { modal.style.display = 'none'; }

  async function sendCreate(product) {
    const fd = new FormData();
    for (const k in product) { fd.append(k, product[k]); }
    const r = await fetch('create_product.php', { method: 'POST', body: fd });
    const txt = await r.text();
    if (!r.ok) throw new Error(txt);
    return JSON.parse(txt);
  }

  document.querySelectorAll('.create-single').forEach(btn => {
    btn.addEventListener('click', async e => {
      e.preventDefault();
      const row = btn.closest('tr');
      const product = {
        gtin: row.dataset.gtin,
        name: row.dataset.name,
        tnved: row.dataset.tnved,
        article: row.dataset.article
      };
      showModal('Создание товара...');
      try {
        const res = await sendCreate(product);
        showModal(res.status === 'ok' ? 'Товар создан' : ('Ошибка: ' + (res.error || '')));
      } catch (err) {
        showModal('Ошибка: ' + err.message);
      }
      setTimeout(hideModal, 2000);
    });
  });

  const selectAll = document.getElementById('selectAll');
  const getItemCheckboxes = () => Array.from(document.querySelectorAll('.select-item'));
  const getSelectedRows = () => getItemCheckboxes()
    .filter(cb => cb.checked)
    .map(cb => cb.closest('tr'))
    .filter(Boolean);

  function updateSelectAllState() {
    const boxes = getItemCheckboxes();
    const total = boxes.length;
    const checked = boxes.filter(cb => cb.checked).length;
    if (!selectAll) return;
    selectAll.checked = total > 0 && checked === total;
    selectAll.indeterminate = checked > 0 && checked < total;
  }

  if (selectAll) {
    selectAll.addEventListener('change', e => {
      getItemCheckboxes().forEach(c => { c.checked = e.target.checked; });
      updateSelectAllState();
    });
  }
  getItemCheckboxes().forEach(cb => cb.addEventListener('change', updateSelectAllState));
  updateSelectAllState();

  const createSelectedBtn = document.getElementById('createSelected');
  if (createSelectedBtn) {
    createSelectedBtn.addEventListener('click', () => {
      const rows = getSelectedRows();
      if (!rows.length) { showModal('Нет выбранных карточек'); setTimeout(hideModal, 1500); return; }
      const list = rows.map(r => (r.dataset.gtin || '') + ' ' + (r.dataset.name || '')).join('<br>');
      showModal('<div style="max-height:300px;overflow:auto">' + list + '</div><button id="confirmMass">Подтвердить</button>');
      const confirmBtn = document.getElementById('confirmMass');
      if (!confirmBtn) return;
      confirmBtn.addEventListener('click', async () => {
        showModal('Создание товаров...');
        const items = rows.map(r => ({
          gtin: r.dataset.gtin,
          name: r.dataset.name,
          tnved: r.dataset.tnved,
          article: r.dataset.article
        }));
        try {
          const resp = await fetch('create_products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items })
          });
          const txt = await resp.text();
          if (!resp.ok) throw new Error(txt);
          const data = JSON.parse(txt);
          const out = data.results.map(r => r.status === 'ok' ? '✅ ' + r.gtin : '❌ ' + r.gtin + ': ' + r.error).join('<br>');
          showModal(out);
        } catch (err) {
          showModal('Ошибка: ' + err.message);
        }
      }, { once: true });
    });
  }

  const searchInput = document.querySelector('.filter input[name="q"]');
  const tableRows = Array.from(document.querySelectorAll('tbody tr'));

  function filterRows() {
    if (!searchInput) return;
    const q = searchInput.value.trim().toLowerCase();
    if (q === '') {
      tableRows.forEach(r => { r.style.display = ''; });
      return;
    }
    tableRows.forEach(r => {
      const text = [
        r.dataset.id || '',
        r.dataset.gtin || '',
        r.dataset.name || '',
        r.dataset.tnved || '',
        r.dataset.article || ''
      ].join(' ').toLowerCase();
      r.style.display = text.includes(q) ? '' : 'none';
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', filterRows);
    window.addEventListener('DOMContentLoaded', filterRows);
    filterRows();
  }

  const signCertSelect = document.getElementById('signCert');
  const signLog = document.getElementById('signLog');
  const signButton = document.getElementById('signSelectedBtn');
  const loadAwaitingBtn = document.getElementById('loadAwaiting');
  let certs = [];

  function logLine(msg) {
    if (!signLog) return;
    signLog.textContent += (signLog.textContent ? '\n' : '') + msg;
    signLog.scrollTop = signLog.scrollHeight;
  }

  function resetLog() {
    if (!signLog) return;
    signLog.textContent = '';
  }

  async function loadCertificates() {
    if (!signCertSelect) return;
    certs = [];
    signCertSelect.innerHTML = '';
    let store;
    try {
      store = await cadesplugin.CreateObjectAsync('CAdESCOM.Store');
      await store.Open(2, 'My', 2);
      const col = await store.Certificates;
      const cnt = await col.Count;
      for (let i = 1; i <= cnt; i++) {
        const cert = await col.Item(i);
        const validTo = new Date(await cert.ValidToDate);
        if (validTo < new Date()) continue;
        const subject = await cert.SubjectName;
        certs.push(cert);
        signCertSelect.add(new Option(subject, String(certs.length - 1)));
      }
      if (!certs.length) {
        signCertSelect.add(new Option('Нет действующих сертификатов', ''));
        logLine('⚠️ Действующих сертификатов не найдено');
      } else {
        signCertSelect.selectedIndex = 0;
        logLine('✅ Сертификаты загружены');
      }
    } catch (e) {
      logLine('❌ CryptoPro: ' + (e.message || e));
      signCertSelect.add(new Option('Ошибка загрузки сертификатов', ''));
    } finally {
      if (store) {
        try { await store.Close(); } catch { /* ignore */ }
      }
      if (signButton) {
        signButton.disabled = certs.length === 0;
      }
    }
  }

  if (signButton) {
    signButton.disabled = true;
  }

  if (typeof cadesplugin !== 'undefined' && typeof cadesplugin.then === 'function') {
    cadesplugin.then(loadCertificates).catch(e => {
      logLine('❌ CryptoPro: ' + (e.message || e));
      if (signButton) signButton.disabled = true;
    });
  } else {
    logLine('❌ Плагин CryptoPro недоступен');
  }

  const utf8ToB64 = s => window.btoa(unescape(encodeURIComponent(s)));
  function isBase64(str) {
    return /^[0-9A-Za-z+/]+={0,2}$/.test(str.replace(/\s+/g, ''));
  }

  async function signDetached(xmlB64, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    try {
      if (typeof sd.propset_ContentEncoding === 'function') {
        await sd.propset_ContentEncoding(cadesplugin.CADESCOM_BASE64_TO_BINARY);
      }
      await sd.propset_Content(xmlB64);
      const pkcs7 = await sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES, true);
      return { pkcs7 };
    } catch (e1) {
      logLine('ℹ️ attempt-1: ' + (e1.message || e1));
    }
    await sd.propset_Content(window.atob(xmlB64));
    const pkcs7 = await sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES, true);
    return { pkcs7 };
  }

  async function refreshAwaiting({ log = true, autoSelect = true } = {}) {
    if (!loadAwaitingBtn) return { total: 0, matched: 0 };
    loadAwaitingBtn.disabled = true;
    try {
      const resp = await fetch('list_for_sign.php');
      const txt = await resp.text();
      if (!resp.ok) throw new Error(`list_for_sign ${resp.status}\n${txt}`);
      const data = JSON.parse(txt);
      if (data?.error) throw new Error(data.error);
      if (!Array.isArray(data)) throw new Error('Некорректный ответ');
      const ids = new Set(data.map(item => String(item.goodId)));
      let matched = 0;
      document.querySelectorAll('tbody tr').forEach(row => {
        const match = ids.has(row.dataset.id);
        row.classList.toggle('needs-sign', match);
        if (autoSelect) {
          const cb = row.querySelector('.select-item');
          if (cb) cb.checked = match;
        }
        if (match) matched++;
      });
      updateSelectAllState();
      if (log) {
        logLine(`ℹ️ Карточек, ожидающих подписи: ${data.length}. На этой странице: ${matched}.`);
        if (data.length && !matched) {
          logLine('ℹ️ Карточки есть, но они находятся вне текущей страницы.');
        }
      }
      return { total: data.length, matched };
    } catch (err) {
      if (log) logLine('❌ list_for_sign: ' + (err.message || err));
      throw err;
    } finally {
      loadAwaitingBtn.disabled = false;
    }
  }

  if (loadAwaitingBtn) {
    loadAwaitingBtn.addEventListener('click', () => {
      refreshAwaiting({ log: true, autoSelect: true }).catch(() => {});
    });
  }

  if (signButton) {
    signButton.addEventListener('click', async () => {
      resetLog();
      logLine('=== Новый запуск ===');
      const selectedValue = signCertSelect ? signCertSelect.value : '';
      const idx = selectedValue === '' ? -1 : Number(selectedValue);
      const cert = idx >= 0 ? certs[idx] : undefined;
      if (!cert) {
        logLine('❌ Выберите сертификат');
        if (signButton) signButton.disabled = certs.length === 0;
        return;
      }
      const rows = getSelectedRows();
      if (!rows.length) {
        logLine('❌ Нет выбранных карточек');
        return;
      }
      const ids = [...new Set(rows.map(r => r.dataset.id).filter(Boolean))];
      if (!ids.length) {
        logLine('❌ Не найдены ID карточек');
        return;
      }
      signButton.disabled = true;
      try {
        const xResp = await fetch('get_xml.php?ids=' + ids.join(','));
        const xTxt = await xResp.text();
        if (!xResp.ok) throw new Error(`get_xml ${xResp.status}\n${xTxt}`);
        const list = JSON.parse(xTxt);
        if (list?.error) { logLine('❌ ' + list.error); return; }
        if (!Array.isArray(list) || !list.length) { logLine('✋ Нечего подписывать'); return; }
        logLine('ℹ️ К подписи: ' + list.length);
        const pack = [];
        for (const item of list) {
          try {
            const src = item.xmlB64 ?? item.xml ?? '';
            const xmlB64 = isBase64(src) ? src.replace(/\s+/g, '') : utf8ToB64(src);
            const { pkcs7 } = await signDetached(xmlB64, cert);
            pack.push({ goodId: item.goodId, base64Xml: xmlB64, signature: pkcs7 });
            logLine('✅ ' + item.goodId);
          } catch (e) {
            logLine('🔴 ' + item.goodId + ': ' + (e.message || e));
          }
        }
        if (!pack.length) { logLine('✋ Нечего отправлять'); return; }
        const apiResp = await fetch('send_signature.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ signPack: pack })
        });
        const apiRaw = await apiResp.text();
        logLine('API:\n' + apiRaw);
        try {
          const parsed = JSON.parse(apiRaw);
          if (parsed.signed?.length) logLine('🌿 Подписано: ' + parsed.signed.join(', '));
          if (parsed.errors?.length) logLine('⚠️ Ошибки:\n' + JSON.stringify(parsed.errors, null, 2));
        } catch (e) { /* ignore */ }
        getItemCheckboxes().forEach(cb => { cb.checked = false; });
        updateSelectAllState();
        await refreshAwaiting({ log: false, autoSelect: false });
      } catch (err) {
        logLine('❌ ' + (err.message || err));
      } finally {
        if (signButton) signButton.disabled = certs.length === 0;
      }
    });
  }
})();
</script>

</body>
</html>
