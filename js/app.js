const NOMES = { usuarios: 'Usuários', alunos: 'Alunos', funcionarios: 'Funcionários', treinos: 'Treinos', financeiro: 'Financeiro' };
const MODS = { usuarios: viewUsuarios };   // demais módulos serão registrados aqui
let ME;

const $ = id => document.getElementById(id);
function modal(html) { $('mbody').innerHTML = html; $('modal').classList.add('open'); }
function closeModal() { $('modal').classList.remove('open'); }
$('modal').onclick = e => { if (e.target.id === 'modal') closeModal(); };
$('out').onclick = async () => { await api('api/logout.php'); location = 'index.html'; };

(async () => {
  try { ME = await api('api/me.php'); } catch { location = 'index.html'; return; }
  $('who').textContent = `${ME.nome} (${ME.perfil})`;
  const mods = ME.modulos.filter(m => MODS[m]);
  $('nav').innerHTML = mods.map(m => `<button data-m="${m}">${NOMES[m]}</button>`).join('');
  $('nav').onclick = e => { const m = e.target.dataset.m; if (!m) return;
    [...$('nav').children].forEach(b => b.classList.toggle('on', b === e.target)); MODS[m](); };
  if (mods.length) $('nav').firstChild.click(); else $('main').innerHTML = '<div class="card">Nenhum módulo disponível para seu perfil.</div>';
})();

async function viewUsuarios() {
  const [us, perfis] = await Promise.all([api('api/usuarios.php'), api('api/perfis.php')]);
  $('main').innerHTML = `
  <div class="card"><div class="row"><h3>Usuários</h3><div>
    <button id="nu">Novo</button> <button class="alt" id="ni">Convidar</button>
    ${ME.master ? '<button class="alt" id="np">Perfis</button>' : ''}</div></div>
    <div class="tbl"><table><tr><th>Nome</th><th>Email</th><th>Celular</th><th>Perfil</th><th>Ativo</th><th></th></tr>
    ${us.map(u => `<tr><td>${esc(u.nome)}</td><td>${esc(u.email)}</td><td>${esc(u.celular)}</td><td>${esc(u.perfil)}</td><td>${u.ativo == 1 ? 'sim' : 'não'}</td>
      <td><button class="alt" data-e="${u.id}">Editar</button></td></tr>`).join('')}</table></div></div>`;
  const opts = sel => perfis.map(p => `<option value="${p.id}" ${p.id == sel ? 'selected' : ''}>${esc(p.nome)}</option>`).join('');
  const form = u => modal(`<h3>${u ? 'Editar' : 'Novo'} usuário</h3><form id="uf">
    <label>Nome</label><input name="nome" required value="${esc(u?.nome)}">
    <label>Email</label><input name="email" type="email" required value="${esc(u?.email)}">
    <label>Celular</label><input name="celular" value="${esc(u?.celular)}">
    <label>Senha ${u ? '(vazio = manter)' : '(mín. 8)'}</label><input name="senha" type="password" ${u ? '' : 'required'} minlength="8">
    <label>Perfil</label><select name="id_perfil">${opts(u?.id_perfil)}</select>
    ${u ? `<label class="chk"><input type="checkbox" name="ativo" ${u.ativo == 1 ? 'checked' : ''}> Ativo</label>` : ''}
    <div class="err" id="ue"></div><div class="row"><button>Salvar</button><button type="button" class="danger" onclick="closeModal()">Cancelar</button></div></form>`);
  const bind = u => { $('uf').onsubmit = async e => { e.preventDefault();
    const d = Object.fromEntries(new FormData($('uf'))); if (u) { d.id = u.id; d.ativo = $('uf').ativo.checked ? 1 : 0; }
    try { await api('api/usuarios.php', 'POST', d); closeModal(); viewUsuarios(); } catch (x) { $('ue').textContent = x.message; } }; };
  $('nu').onclick = () => { form(); bind(); };
  document.querySelectorAll('[data-e]').forEach(b => b.onclick = () => { const u = us.find(x => x.id == b.dataset.e); form(u); bind(u); });
  $('ni').onclick = () => { modal(`<h3>Convidar</h3><form id="cf"><label>Email</label><input name="email" type="email" required>
    <label>Perfil</label><select name="id_perfil">${opts()}</select><div class="err" id="ce"></div>
    <div class="row"><button>Gerar convite</button><button type="button" class="danger" onclick="closeModal()">Fechar</button></div></form>`);
    $('cf').onsubmit = async e => { e.preventDefault(); try {
      const r = await api('api/convites.php', 'POST', Object.fromEntries(new FormData($('cf'))));
      const url = new URL(r.link, location.href).href;
      $('ce').style.color = 'var(--neon)'; $('ce').innerHTML = `Link (válido por 7 dias):<br><input readonly value="${esc(url)}" onfocus="this.select()">`;
    } catch (x) { $('ce').textContent = x.message; } }; };
  if (ME.master) $('np').onclick = () => viewPerfis(perfis);
}

function viewPerfis(perfis) {
  modal(`<h3>Perfis de acesso</h3>${perfis.map(p => `<div class="row" style="margin:6px 0"><span>${esc(p.nome)} <small style="color:var(--mut)">${p.master ? '(todos)' : esc(p.modulos.join(', '))}</small></span>
    ${p.master ? '' : `<span><button class="alt" data-pe="${p.id}">Editar</button> <button class="danger" data-pd="${p.id}">X</button></span>`}</div>`).join('')}
    <div class="row"><button id="pn">Novo perfil</button><button class="danger" onclick="closeModal();viewUsuarios()">Fechar</button></div>`);
  const edit = p => { modal(`<h3>${p ? 'Editar' : 'Novo'} perfil</h3><form id="pf"><label>Nome</label><input name="nome" required value="${esc(p?.nome)}">
    <label>Módulos</label>${Object.keys(NOMES).map(m => `<label class="chk"><input type="checkbox" value="${m}" ${p?.modulos.includes(m) ? 'checked' : ''}> ${NOMES[m]}</label>`).join('')}
    <div class="err" id="pe"></div><div class="row"><button>Salvar</button><button type="button" class="danger" onclick="closeModal();viewUsuarios()">Cancelar</button></div></form>`);
    $('pf').onsubmit = async e => { e.preventDefault(); try {
      await api('api/perfis.php', 'POST', { id: p?.id, nome: $('pf').nome.value, modulos: [...$('pf').querySelectorAll('[type=checkbox]:checked')].map(c => c.value) });
      closeModal(); viewUsuarios(); } catch (x) { $('pe').textContent = x.message; } }; };
  $('pn').onclick = () => edit();
  document.querySelectorAll('[data-pe]').forEach(b => b.onclick = () => edit(perfis.find(p => p.id == b.dataset.pe)));
  document.querySelectorAll('[data-pd]').forEach(b => b.onclick = async () => { try { await api('api/perfis.php?id=' + b.dataset.pd, 'DELETE'); closeModal(); viewUsuarios(); } catch (x) { alert(x.message); } });
}
