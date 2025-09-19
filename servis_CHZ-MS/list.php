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
</style>
</head>
<body>

<h2>Всего карточек: <?=isset($total)?$total:count($cards)?></h2>
<form method="get" class="filter">
  <input type="text" name="q" placeholder="Поиск" value="<?=htmlspecialchars($search)?>">
  <input type="datetime-local" name="from" value="<?=htmlspecialchars($fromRaw)?>">
  <input type="datetime-local" name="to"   value="<?=htmlspecialchars($toRaw)?>">
  <button type="submit">Найти</button>
</form>
<p><a href="./sign.html" target="_blank">⭢ Подписать ожидающие карточки</a></p>

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
<tr class="<?=$cls?>" data-gtin="<?=htmlspecialchars(gtin($row))?>" data-name="<?=htmlspecialchars($row['good_name'] ?? '')?>" data-tnved="<?=htmlspecialchars($tn10)?>" data-article="<?=htmlspecialchars($art)?>">
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

<button id="createSelected" style="margin-top:1rem">Создать выбранные</button>
<div id="modal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;align-items:center;justify-content:center;background:rgba(0,0,0,.4)">
  <div class="modal-content" style="background:#fff;padding:1rem;border-radius:4px;position:relative">
    <button class="close-modal" style="position:absolute;top:.2rem;right:.2rem;border:0;background:red;font-size:0.5rem;cursor:pointer">&times;</button>
    <div id="modal-body"></div>
  </div>
</div>

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
const modal = document.getElementById('modal');
const modalBody = modal.querySelector('#modal-body');
modal.querySelector('.close-modal').addEventListener('click', hideModal);
modal.addEventListener('click', e => { if(e.target === modal) hideModal(); });
function showModal(html){ modalBody.innerHTML = html; modal.style.display='flex'; }
function hideModal(){ modal.style.display='none'; }

async function sendCreate(product){
  const fd = new FormData();
  for(const k in product){ fd.append(k, product[k]); }
  const r = await fetch('create_product.php',{method:'POST', body: fd});
  const txt = await r.text();
  if(!r.ok) throw new Error(txt);
  return JSON.parse(txt);
}

document.querySelectorAll('.create-single').forEach(btn=>{
  btn.addEventListener('click', async e=>{
    e.preventDefault();
    const row = btn.closest('tr');
    const product = {
      gtin: row.dataset.gtin,
      name: row.dataset.name,
      tnved: row.dataset.tnved,
      article: row.dataset.article
    };
    showModal('Создание товара...');
    try{
      const res = await sendCreate(product);
      showModal(res.status==='ok'? 'Товар создан' : ('Ошибка: '+(res.error||'')));
    }catch(err){
      showModal('Ошибка: '+err.message);
    }
    setTimeout(hideModal,2000);
  });
});

document.getElementById('selectAll').addEventListener('change', e=>{
  document.querySelectorAll('.select-item').forEach(c=>{ c.checked = e.target.checked; });
});

document.getElementById('createSelected').addEventListener('click', ()=>{
  const rows = Array.from(document.querySelectorAll('.select-item:checked')).map(c=>c.closest('tr'));
  if(!rows.length){ showModal('Нет выбранных карточек'); setTimeout(hideModal,1500); return; }
  const list = rows.map(r=>r.dataset.gtin + ' ' + r.dataset.name).join('<br>');
  showModal('<div style="max-height:300px;overflow:auto">'+list+'</div><button id="confirmMass">Подтвердить</button>');
  document.getElementById('confirmMass').addEventListener('click', async ()=>{
    showModal('Создание товаров...');
    const items = rows.map(r=>({gtin:r.dataset.gtin,name:r.dataset.name,tnved:r.dataset.tnved,article:r.dataset.article}));
    try{
      const resp = await fetch('create_products.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({items})
      });
      const txt = await resp.text();
      if(!resp.ok) throw new Error(txt);
      const data = JSON.parse(txt);
      const out = data.results.map(r=> r.status==='ok'? '✅ '+r.gtin : '❌ '+r.gtin+': '+r.error).join('<br>');
      showModal(out);
    }catch(err){
      showModal('Ошибка: '+err.message);
    }
  }, {once:true});
});

const searchInput = document.querySelector('.filter input[name="q"]');
const tableRows = Array.from(document.querySelectorAll('tbody tr'));

function filterRows() {
  const q = searchInput.value.trim().toLowerCase();
  if (q === '') {
    tableRows.forEach(r => r.style.display = '');
    return;
  }
  tableRows.forEach(r => {
    const text = [
      r.dataset.gtin || '',
      r.dataset.name || '',
      r.dataset.tnved || '',
      r.dataset.article || ''
    ].join(' ').toLowerCase();
    r.style.display = text.includes(q) ? '' : 'none';
  });
}

searchInput.addEventListener('input', filterRows);
window.addEventListener('DOMContentLoaded', filterRows);
</script>

</body>
</html>
