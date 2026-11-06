<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name') }} — Espace responsable</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 0 16px; }
  h1 { font-size: 20px; }
  h2 { font-size: 16px; margin-top: 30px; }
  input, select { padding: 8px; margin: 4px 0; box-sizing: border-box; }
  button { padding: 10px 16px; background: #1f3864; color: white; border: none; border-radius: 6px; cursor: pointer; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
  th { background: #f5f5f5; }
  .erreur { color: #c0392b; }
  .cache { display: none; }
  .stat { display: inline-block; margin-right: 24px; font-size: 15px; }
  .stat b { font-size: 22px; display: block; }
</style>
</head>
<body>
<thead><tr><th>Nom</th><th>Prénom</th><th>Matricule</th><th>Email</th><th>Statut</th><th>Droit de vote</th></tr></thead>

<!-- Connexion -->
<div id="bloc-connexion">
  <h1>Connexion responsable</h1>
  <input type="text" id="utilisateur" placeholder="Nom d'utilisateur"><br>
  <input type="password" id="motdepasse" placeholder="Mot de passe"><br>
  <button onclick="connecter()">Se connecter</button>
  <div class="erreur" id="erreur-connexion"></div>
</div>

<!-- Tableau de bord -->
<div id="bloc-tableau" class="cache">
  <button type="button" onclick="deconnecter()">Se déconnecter</button>
  <h1>Tableau de bord — Élection</h1>

  <div class="stat"><b id="stat-votants">-</b> Votants</div>
  <div class="stat"><b id="stat-restants">-</b> Restants</div>

  <h2>Résultats par candidat</h2>
  <table id="table-resultats">
    <thead><tr><th>Candidat</th><th>Voix</th><th>Action</th></tr></thead>
    <tbody></tbody>
  </table>

  <h2>Ajouter un candidat</h2>
<select id="nouveau-candidat"></select>
<button onclick="ajouterCandidat()">Ajouter</button>

  <h2>Importer la liste des électeurs</h2>
  <input type="file" id="fichier-electeurs" accept=".csv,.xlsx">
  <button onclick="importerElecteurs()">Importer</button>
  <div class="erreur" id="message-import"></div>
  
<h2>Liste des électeurs</h2>
  <table id="table-electeurs">
    <thead><tr><th>Nom</th><th>Prénom</th><th>Matricule</th><th>Email</th><th>Statut</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
const ID_SCRUTIN = 1;
const fetchWithCsrf = (url, options = {}) => {
  const headers = new Headers(options.headers || {});
  headers.set('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
  return fetch(url, { ...options, headers });
};
function updateCsrfToken(token) {
  if (token) document.querySelector('meta[name="csrf-token"]').content = token;
}
function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[char]);
}

async function connecter() {
  const body = JSON.stringify({
    nom_utilisateur: document.getElementById('utilisateur').value,
    mot_de_passe: document.getElementById('motdepasse').value,
  });
  const reponse = await fetchWithCsrf('/responsable/connexion', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'same-origin',
    body,
  });
  const data = await reponse.json();
  if (!reponse.ok) {
    document.getElementById('erreur-connexion').textContent =
  reponse.status === 429 ? 'Trop de tentatives, réessayez dans une minute.' : data.erreur;
  }
  if (!reponse.ok) return;
  updateCsrfToken(data.csrf_token);
  document.getElementById('bloc-connexion').classList.add('cache');
  document.getElementById('bloc-tableau').classList.remove('cache');
  chargerTableauDeBord();
  chargerElecteurs();
  setInterval(chargerTableauDeBord, 5000);
}

async function deconnecter() {
  const reponse = await fetchWithCsrf('/responsable/deconnexion', {
    method: 'POST', headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
  });
  const data = await reponse.json();
  updateCsrfToken(data.csrf_token);
  document.getElementById('bloc-tableau').classList.add('cache');
  document.getElementById('bloc-connexion').classList.remove('cache');
}

async function chargerTableauDeBord() {
  const reponse = await fetchWithCsrf(`/responsable/tableau-de-bord?id_scrutin=${ID_SCRUTIN}`, {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
  const data = await reponse.json();
  document.getElementById('stat-votants').textContent = data.votants;
  document.getElementById('stat-restants').textContent = data.restants;

  const corps = document.querySelector('#table-resultats tbody');
  corps.innerHTML = '';
  data.resultats.forEach(c => {
    corps.innerHTML += `<tr><td>${escapeHtml(c.nom_candidat)}</td><td>${escapeHtml(c.voix)}</td><td><button type="button" onclick="supprimerCandidat(${Number(c.id)})">Supprimer</button></td></tr>`;
  });
}

async function chargerElecteurs() {
  const reponse = await fetchWithCsrf('/responsable/electeurs', {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
  const data = await reponse.json();
  const corps = document.querySelector('#table-electeurs tbody');
  corps.innerHTML = '';
  data.forEach(e => {
    const statut = e.a_vote
      ? '<span style="color:#1a7a3d; font-weight:bold;">✓ A voté</span>'
      : '<span style="color:#999;">En attente</span>';
    const droitVote = e.peut_voter
      ? `<button onclick="basculerDroitVote(${e.id})" style="background:#c0392b;">Retirer le droit</button>`
      : `<button onclick="basculerDroitVote(${e.id})" style="background:#1a7a3d;">Rétablir le droit</button>`;
    corps.innerHTML += `<tr><td>${escapeHtml(e.nom)}</td><td>${escapeHtml(e.prenom)}</td><td>${escapeHtml(e.matricule)}</td><td>${escapeHtml(e.email)}</td><td>${statut}</td><td>${droitVote}</td></tr>`;
  });

  const menuCandidat = document.getElementById('nouveau-candidat');
  menuCandidat.innerHTML = '<option value="">-- Choisir un électeur --</option>' +
    data.map(e => `<option value="${Number(e.id)}">${escapeHtml(e.nom)} ${escapeHtml(e.prenom)} (${escapeHtml(e.matricule)})</option>`).join('');
}
async function ajouterCandidat() {
  const idElecteur = document.getElementById('nouveau-candidat').value;
  if (!idElecteur) return;

  const body = JSON.stringify({
    id_scrutin: ID_SCRUTIN,
    id_electeur: idElecteur,
  });
  const reponse = await fetchWithCsrf('/responsable/candidats', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'same-origin',
    body,
  });
  const data = await reponse.json();
  if (!reponse.ok) {
    alert(data.erreur);
    return;
  }
  document.getElementById('nouveau-candidat').value = '';
  chargerTableauDeBord();
}

async function supprimerCandidat(id) {
  if (!confirm('Confirmer la suppression de ce candidat ?')) return;

  const reponse = await fetchWithCsrf(`/responsable/candidats/${Number(id)}`, {
    method: 'DELETE',
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
  const data = await reponse.json();

  if (!reponse.ok) {
    alert(data.erreur);
    return;
  }
  chargerTableauDeBord();
}

async function importerElecteurs() {
  const input = document.getElementById('fichier-electeurs');
  if (!input.files.length) {
    document.getElementById('message-import').textContent = 'Choisissez un fichier d\'abord.';
    return;
  }

  const formData = new FormData();
  formData.append('fichier', input.files[0]);

  const reponse = await fetchWithCsrf('/responsable/import-electeurs', {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
    body: formData,
  });
  const data = await reponse.json();

  if (!reponse.ok) {
    document.getElementById('message-import').textContent = data.erreur || 'Erreur lors de l\'import.';
    return;
  }

  document.getElementById('message-import').textContent = `${data.importes} électeur(s) importé(s) avec succès.`;
  input.value = '';
  chargerElecteurs();
}
async function verifierStatutConnexion() {
  const reponse = await fetch('/responsable/statut', {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
  const data = await reponse.json();

  if (data.connecte) {
    document.getElementById('bloc-connexion').classList.add('cache');
    document.getElementById('bloc-tableau').classList.remove('cache');
    chargerTableauDeBord();
    chargerElecteurs();
    setInterval(chargerTableauDeBord, 5000);
  }
}
async function basculerDroitVote(id) {
  await fetchWithCsrf(`/responsable/electeurs/${Number(id)}/droit-vote`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
  chargerElecteurs();
}

verifierStatutConnexion();
</script>
</body>
</html>
