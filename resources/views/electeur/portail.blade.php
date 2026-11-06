<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name') }} — Portail électeur</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 420px; margin: 40px auto; padding: 0 16px; }
  h1 { font-size: 20px; }
  input, select { width: 100%; padding: 10px; margin: 6px 0 14px; box-sizing: border-box; }
  button { width: 100%; padding: 12px; background: #1f3864; color: white; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
  button:disabled { background: #999; }
  .erreur { color: #c0392b; margin-top: 8px; }
  .succes { color: #1a7a3d; margin-top: 8px; }
  .etape { display: none; }
  .etape.actif { display: block; }
</style>
</head>
<body>

<h1>Élection du délegue de classe 2026</h1>

<!-- Étape 1 : identité -->
<div class="etape actif" id="etape-identite">
  <label>Nom</label>
  <input type="text" id="nom" autocomplete="family-name" required>
  <label>Prénom</label>
  <input type="text" id="prenom" autocomplete="given-name" required>
  <label>Matricule</label>
  <input type="text" id="matricule" autocomplete="off" required>
  <button onclick="envoyerIdentite()">Recevoir mon code</button>
  <div class="erreur" id="erreur-identite"></div>
</div>

<!-- Étape 2 : code OTP -->
<div class="etape" id="etape-otp">
  <p>Un code a été envoyé à votre adresse e-mail.</p>
  <label>Code de vérification</label>
  <input type="text" id="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
  <button onclick="verifierCode()">Valider le code</button>
  <div class="erreur" id="erreur-otp"></div>
</div>

<!-- Étape 3 : vote -->
<div class="etape" id="etape-vote">
  <label>Choisissez votre candidat</label>
  <div id="liste-candidats"></div>
  <button onclick="voter()">Confirmer mon vote</button>
  <div class="erreur" id="erreur-vote"></div>
</div>

<!-- Étape 4 : confirmation -->
<div class="etape" id="etape-confirmation">
  <p class="succes">Votre vote a bien été enregistré.</p>
  <p id="numero-confirmation"></p>
</div>

<script>
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

function afficherEtape(id) {
  document.querySelectorAll('.etape').forEach(e => e.classList.remove('actif'));
  document.getElementById(id).classList.add('actif');
}

async function envoyerIdentite() {
  const body = JSON.stringify({
    nom: document.getElementById('nom').value,
    prenom: document.getElementById('prenom').value,
    matricule: document.getElementById('matricule').value,
  });
  const reponse = await fetchWithCsrf('/connexion', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'same-origin',
    body,
  });
  const data = await reponse.json();
  if (!reponse.ok) {
    document.getElementById('erreur-identite').textContent = data.erreur;
    return;
  }
  afficherEtape('etape-otp');
}

async function verifierCode() {
  const body = JSON.stringify({
    code: document.getElementById('code').value,
  });
  const reponse = await fetchWithCsrf('/verifier-otp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'same-origin',
    body,
  });
  const data = await reponse.json();
  if (!reponse.ok) {
    document.getElementById('erreur-otp').textContent = data.erreur;
    return;
  }
  updateCsrfToken(data.csrf_token);
  afficherEtape('etape-vote');
}

async function voter() {
  const choix = document.querySelector('input[name="candidat"]:checked');
  if (!choix) {
    document.getElementById('erreur-vote').textContent = 'Veuillez choisir un candidat.';
    return;
  }
  const body = JSON.stringify({
    id_scrutin: 1,
    id_candidat: choix.value,
  });
  const reponse = await fetchWithCsrf('/voter', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'same-origin',
    body,
  });
  const data = await reponse.json();
  if (!reponse.ok) {
    document.getElementById('erreur-vote').textContent = data.erreur;
    return;
  }
  document.getElementById('numero-confirmation').textContent = 'Confirmation : ' + data.confirmation;
  afficherEtape('etape-confirmation');
}
async function chargerCandidats() {
  const reponse = await fetchWithCsrf('/candidats-scrutin', {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
  const candidats = await reponse.json();
  const conteneur = document.getElementById('liste-candidats');
  conteneur.innerHTML = candidats.map(c => `
    <label style="display:block; padding:8px; border:1px solid #ddd; border-radius:6px; margin-bottom:6px; cursor:pointer;">
      <input type="radio" name="candidat" value="${Number(c.id)}"> ${escapeHtml(c.nom_candidat)}
    </label>
  `).join('');
}

chargerCandidats();

</script>
</body>
</html>
