const NOMES = { usuarios: 'Usuários', alunos: 'Alunos', funcionarios: 'Funcionários', treinos: 'Treinos', financeiro: 'Financeiro' };
const MODS = { usuarios: viewUsuarios, alunos: viewAlunos, treinos: viewTreinos, funcionarios: viewFuncionarios };   // demais módulos serão registrados aqui
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

const brl = v => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

async function viewAlunos() {
  const al = await api('api/alunos.php');
  $('main').innerHTML = `
  <div class="card"><div class="row"><h3>Alunos</h3><button id="na">Novo aluno</button></div>
    <div class="tbl"><table><tr><th>Matrícula</th><th>Nome</th><th>Telefone</th><th>Contrato</th><th>Valor</th><th>Venc.</th><th>Ativo</th><th></th></tr>
    ${al.map(a => `<tr><td>${esc(a.matricula)}</td><td>${esc(a.nome)}</td><td>${esc(a.telefone)}</td><td>${esc(a.contrato)}</td>
      <td>${brl(a.valor_combinado)}</td><td>dia ${a.dia_vencimento}</td><td>${a.ativo == 1 ? 'sim' : 'não'}</td>
      <td><button class="alt" data-ea="${a.id}">Editar</button></td></tr>`).join('') || '<tr><td colspan="8" style="color:var(--mut)">Nenhum aluno cadastrado.</td></tr>'}</table></div></div>`;
  const form = async a => {
    // quem não tem acesso ao módulo Usuários ainda pode salvar o aluno, só não vincula login
    const us = await api('api/alunos.php?usuarios=' + (a?.id_usuario || 0));
    modal(`<h3>${a ? 'Editar' : 'Novo'} aluno</h3><form id="af">
      <label>Nome</label><input name="nome" required value="${esc(a?.nome)}">
      <label>Telefone</label><input name="telefone" value="${esc(a?.telefone)}">
      <label>Endereço</label><input name="endereco" value="${esc(a?.endereco)}">
      <label>Contrato</label><input name="contrato" placeholder="Mensal, Trimestral, Anual..." value="${esc(a?.contrato)}">
      <label>Valor combinado (R$)</label><input name="valor_combinado" type="number" step="0.01" min="0" required value="${esc(a?.valor_combinado ?? '')}">
      <label>Dia de vencimento</label><input name="dia_vencimento" type="number" min="1" max="31" required value="${esc(a?.dia_vencimento ?? 10)}">
      <label>Data de cadastro</label><input name="data_cadastro" type="date" required value="${esc(a?.data_cadastro ?? new Date().toISOString().slice(0, 10))}">
      <label>Usuário de acesso (opcional)</label><select name="id_usuario"><option value="">— sem login —</option>
        ${us.map(u => `<option value="${u.id}" ${u.id == a?.id_usuario ? 'selected' : ''}>${esc(u.nome)} (${esc(u.email)})</option>`).join('')}</select>
      ${a ? `<label class="chk"><input type="checkbox" name="ativo" ${a.ativo == 1 ? 'checked' : ''}> Ativo</label>` : ''}
      <div class="err" id="ae"></div><div class="row"><button>Salvar</button><button type="button" class="danger" onclick="closeModal()">Cancelar</button></div></form>`);
    $('af').onsubmit = async e => { e.preventDefault();
      const d = Object.fromEntries(new FormData($('af'))); if (a) { d.id = a.id; d.ativo = $('af').ativo.checked ? 1 : 0; }
      try { await api('api/alunos.php', 'POST', d); closeModal(); viewAlunos(); } catch (x) { $('ae').textContent = x.message; } };
  };
  $('na').onclick = () => form();
  document.querySelectorAll('[data-ea]').forEach(b => b.onclick = () => form(al.find(x => x.id == b.dataset.ea)));
}

async function viewTreinos() {
  const { aluno, treinos } = await api('api/treinos.php');
  const card = t => `<div class="card"><div class="row"><h3>${esc(t.nome)}</h3>${aluno ? '' : `<button class="alt" data-et="${t.id}">Editar</button>`}</div>
    <div style="white-space:pre-wrap;margin:8px 0">${esc(t.descricao) || '<span style="color:var(--mut)">Sem descrição</span>'}</div>
    <small style="color:var(--mut)">${aluno ? 'Professor: ' + esc(t.professor || '—')
      : 'Alunos: ' + (t.alunos.length ? t.alunos.map(a => esc(a.nome)).join(', ') : 'nenhum')}</small></div>`;
  $('main').innerHTML = `<div class="card"><div class="row"><h3>${aluno ? 'Meus treinos' : 'Treinos'}</h3>${aluno ? '' : '<button id="nt">Novo treino</button>'}</div></div>`
    + (treinos.map(card).join('') || '<div class="card" style="color:var(--mut)">Nenhum treino por aqui ainda.</div>');
  if (aluno) return;
  const form = async t => {
    const als = await api('api/treinos.php?alunos=1');
    const sel = new Set((t?.alunos || []).map(a => a.id));
    modal(`<h3>${t ? 'Editar' : 'Novo'} treino</h3><form id="tf">
      <label>Nome</label><input name="nome" required value="${esc(t?.nome)}">
      <label>Descrição</label><textarea name="descricao" rows="7" style="width:100%;padding:9px;background:#0b0d10;color:var(--txt);border:1px solid var(--line);border-radius:4px;font:inherit">${esc(t?.descricao)}</textarea>
      <label>Alunos</label>${als.map(a => `<label class="chk"><input type="checkbox" value="${a.id}" ${sel.has(a.id) ? 'checked' : ''}> ${esc(a.nome)} <small style="color:var(--mut)">${esc(a.matricula)}</small></label>`).join('') || '<small style="color:var(--mut)">Nenhum aluno ativo.</small>'}
      <div class="err" id="te"></div><div class="row"><button>Salvar</button>${t ? '<button type="button" class="danger" id="td">Excluir</button>' : ''}<button type="button" class="alt" onclick="closeModal()">Cancelar</button></div></form>`);
    $('tf').onsubmit = async e => { e.preventDefault();
      try { await api('api/treinos.php', 'POST', { id: t?.id, nome: $('tf').nome.value, descricao: $('tf').descricao.value,
        alunos: [...$('tf').querySelectorAll('[type=checkbox]:checked')].map(c => +c.value) }); closeModal(); viewTreinos(); }
      catch (x) { $('te').textContent = x.message; } };
    if (t) $('td').onclick = async () => { if (!confirm('Excluir este treino?')) return;
      await api('api/treinos.php?id=' + t.id, 'DELETE'); closeModal(); viewTreinos(); };
  };
  $('nt').onclick = () => form();
  document.querySelectorAll('[data-et]').forEach(b => b.onclick = () => form(treinos.find(x => x.id == b.dataset.et)));
}

async function viewFuncionarios() {
  const fs = await api('api/funcionarios.php');
  const fmtCpf = c => c ? c.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '';
  $('main').innerHTML = `
  <div class="card"><div class="row"><h3>Funcionários</h3><button id="nf">Novo funcionário</button></div>
    <div class="tbl"><table><tr><th>Nome</th><th>Cargo</th><th>CPF</th><th>Telefone</th><th>Admissão</th><th>Salário</th><th>Ativo</th><th></th></tr>
    ${fs.map(f => `<tr><td>${esc(f.nome)}</td><td>${esc(f.cargo)}</td><td>${esc(fmtCpf(f.cpf))}</td><td>${esc(f.telefone)}</td>
      <td>${new Date(f.data_admissao + 'T12:00').toLocaleDateString('pt-BR')}</td><td>${brl(f.salario)}</td><td>${f.ativo == 1 ? 'sim' : 'não'}</td>
      <td><button class="alt" data-ef="${f.id}">Editar</button></td></tr>`).join('') || '<tr><td colspan="8" style="color:var(--mut)">Nenhum funcionário cadastrado.</td></tr>'}</table></div></div>`;
  const form = async f => {
    const us = await api('api/funcionarios.php?usuarios=' + (f?.id_usuario || 0));
    modal(`<h3>${f ? 'Editar' : 'Novo'} funcionário</h3><form id="ff">
      <label>Nome</label><input name="nome" required value="${esc(f?.nome)}">
      <label>Cargo</label><input name="cargo" required list="cargos" placeholder="Professor, Secretaria..." value="${esc(f?.cargo)}">
      <datalist id="cargos"><option>Professor</option><option>Secretaria</option><option>Atendente</option><option>Limpeza</option><option>Gerente</option></datalist>
      <label>CPF</label><input name="cpf" inputmode="numeric" maxlength="14" value="${esc(fmtCpf(f?.cpf))}">
      <label>Telefone</label><input name="telefone" value="${esc(f?.telefone)}">
      <label>Endereço</label><input name="endereco" value="${esc(f?.endereco)}">
      <label>Data de admissão</label><input name="data_admissao" type="date" required value="${esc(f?.data_admissao ?? new Date().toISOString().slice(0, 10))}">
      <label>Salário (R$)</label><input name="salario" type="number" step="0.01" min="0" required value="${esc(f?.salario ?? '')}">
      <label>Usuário de acesso (opcional)</label><select name="id_usuario"><option value="">— sem login —</option>
        ${us.map(u => `<option value="${u.id}" ${u.id == f?.id_usuario ? 'selected' : ''}>${esc(u.nome)} (${esc(u.email)})</option>`).join('')}</select>
      ${f ? `<label class="chk"><input type="checkbox" name="ativo" ${f.ativo == 1 ? 'checked' : ''}> Ativo</label>` : ''}
      <div class="err" id="fe"></div><div class="row"><button>Salvar</button><button type="button" class="danger" onclick="closeModal()">Cancelar</button></div></form>`);
    $('ff').onsubmit = async e => { e.preventDefault();
      const d = Object.fromEntries(new FormData($('ff'))); if (f) { d.id = f.id; d.ativo = $('ff').ativo.checked ? 1 : 0; }
      try { await api('api/funcionarios.php', 'POST', d); closeModal(); viewFuncionarios(); } catch (x) { $('fe').textContent = x.message; } };
  };
  $('nf').onclick = () => form();
  document.querySelectorAll('[data-ef]').forEach(b => b.onclick = () => form(fs.find(x => x.id == b.dataset.ef)));
}
