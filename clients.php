<?php
require_once 'config.php';
// clients management UI — uses add_client.php, load_clients.php, update_client.php, delete_client.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Clients — <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="includes/common_styles.css">
  <style>
    body { padding: 0; }
    .btn-small { padding: 6px 12px; font-size: 13px; }
  </style>
</head>
<body>
  <?php include 'includes/header.php'; ?>
  
  <div class="container">
    <h1>Gestion des clients</h1>

    <div class="section">
      <h3>Ajouter / Modifier un client</h3>
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-bottom:8px;">
        <input id="c_nom" placeholder="Nom *" />
        <input id="c_prenom" placeholder="Prénom *" />
        <input id="c_email" placeholder="Email" />
        <input id="c_mobile" placeholder="Téléphone mobile" />
        <input id="c_fixe" placeholder="Téléphone fixe" />
        <input id="c_ville" placeholder="Ville" />
        <input id="c_code_postal" placeholder="Code postal" />
        <input id="c_pays" placeholder="Pays" />
        <input id="c_adresse" placeholder="Adresse complète" style="grid-column: 1 / -1;" />
        <input id="c_etage" placeholder="Étage" />
        <input id="c_code_entree" placeholder="Code entrée" />
        <select id="c_source_acquisition">
          <option value="">Source d'acquisition</option>
          <option value="bouche_a_oreille">Bouche à oreille</option>
          <option value="publicite">Publicité</option>
          <option value="site_web">Site web</option>
          <option value="reseau_social">Réseau social</option>
          <option value="partenaire">Partenaire</option>
          <option value="autre">Autre</option>
        </select>
        <select id="c_mode_paiement">
          <option value="">Mode de paiement</option>
          <option value="especes">Espèces</option>
          <option value="cheque">Chèque</option>
          <option value="virement">Virement</option>
          <option value="carte_bancaire">Carte bancaire</option>
          <option value="avance_immediate">💰 Avance immédiate</option>
        </select>
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" id="c_avance_imme" />
          <span>💚 Avance immédiate activée</span>
        </label>
      </div>
      <div style="display:flex; gap:8px;">
        <button id="btnSaveClient" class="btn btn-success">Ajouter</button>
        <button id="btnCancelEdit" class="btn btn-secondary" style="display:none;">Annuler</button>
      </div>
    </div>

    <div class="section">
      <h3>Liste des clients</h3>
      <input id="q" placeholder="Rechercher nom / prénom / mobile" style="width:100%;padding:8px;margin-bottom:12px;" />
      <table>
        <thead><tr><th>Nom</th><th>Email</th><th>Ville</th><th>Mobile</th><th>Actions</th></tr></thead>
        <tbody id="clientsList">
        </tbody>
      </table>
    </div>
  </div>

  <script>
    let editingId = null;
    function loadClients(q=''){
      const url = 'api/clients.php?action=list&q=' + encodeURIComponent(q);
      fetch(url).then(r=>r.json()).then(json=>{
        const rows = json.clients || [];
        const tbody = document.getElementById('clientsList');
        tbody.innerHTML = '';
        rows.forEach(c=>{
          const tr = document.createElement('tr');
          const name = (c.prenom? c.prenom + ' ' : '') + (c.nom||'');
          tr.innerHTML = '<td>'+escapeHtml(name)+'</td>'+
                         '<td>'+escapeHtml(c.email||'')+'</td>'+
                         '<td>'+escapeHtml(c.ville||'')+'</td>'+
                         '<td>'+escapeHtml(c.telephone_mobile||'')+'</td>'+
                         '<td>'+
                           '<a href="client_dashboard.php?client_id='+c.id+'" class="btn-small" style="background:#2196f3;color:#fff;text-decoration:none;display:inline-block;">Tableau de bord</a> '+
                           '<button class="btn btn-warning btn-small btn-edit" data-id="'+c.id+'">Modifier</button> '+
                           '<button class="btn btn-danger btn-small btn-delete" data-id="'+c.id+'">Supprimer</button>'+
                         '</td>';
          tbody.appendChild(tr);
        });
        // attach handlers
        document.querySelectorAll('.btn-edit').forEach(b=>b.onclick = ()=>{ startEdit(b.getAttribute('data-id')) });
        document.querySelectorAll('.btn-delete').forEach(b=>b.onclick = ()=>{ if(confirm('Supprimer ce client ?')) deleteClient(b.getAttribute('data-id')) });
      }).catch(e=>{ console.error(e); alert('Erreur chargement clients') });
    }

    function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function startEdit(id){
      fetch('api/clients.php?action=get&id=' + encodeURIComponent(id)).then(r=>r.json()).then(json=>{
        const c = json;
        if (!c || !c.id) return alert('Client introuvable');
        editingId = c.id;
        document.getElementById('c_nom').value = c.nom||'';
        document.getElementById('c_prenom').value = c.prenom||'';
        document.getElementById('c_email').value = c.email||'';
        document.getElementById('c_mobile').value = c.telephone_mobile||'';
        document.getElementById('c_fixe').value = c.telephone_fixe||'';
        document.getElementById('c_ville').value = c.ville||'';
        document.getElementById('c_code_postal').value = c.code_postal||'';
        document.getElementById('c_pays').value = c.pays||'';
        document.getElementById('c_adresse').value = c.adresse||'';
        document.getElementById('c_etage').value = c.etage||'';
        document.getElementById('c_code_entree').value = c.code_entree||'';
        document.getElementById('c_source_acquisition').value = c.source_acquisition||'';
        document.getElementById('c_mode_paiement').value = c.mode_paiement||'';
        document.getElementById('c_avance_imme').checked = c.avance_imme == 1;
        document.getElementById('btnSaveClient').textContent = 'Enregistrer';
        document.getElementById('btnCancelEdit').style.display = 'inline-block';
      }).catch(e=>{ console.error(e); alert('Erreur') });
    }

    function clearForm(){ 
      editingId = null; 
      document.getElementById('c_nom').value=''; 
      document.getElementById('c_prenom').value=''; 
      document.getElementById('c_email').value=''; 
      document.getElementById('c_mobile').value=''; 
      document.getElementById('c_fixe').value=''; 
      document.getElementById('c_ville').value=''; 
      document.getElementById('c_code_postal').value=''; 
      document.getElementById('c_pays').value=''; 
      document.getElementById('c_adresse').value=''; 
      document.getElementById('c_etage').value=''; 
      document.getElementById('c_code_entree').value=''; 
      document.getElementById('c_source_acquisition').value=''; 
      document.getElementById('c_mode_paiement').value=''; 
      document.getElementById('c_avance_imme').checked = false;
      document.getElementById('btnSaveClient').textContent='Ajouter'; 
      document.getElementById('btnCancelEdit').style.display='none'; 
    }

    function saveClient(){
      const nom = document.getElementById('c_nom').value.trim();
      const prenom = document.getElementById('c_prenom').value.trim();
      const email = document.getElementById('c_email').value.trim();
      const telephone_mobile = document.getElementById('c_mobile').value.trim();
      const telephone_fixe = document.getElementById('c_fixe').value.trim();
      const ville = document.getElementById('c_ville').value.trim();
      const code_postal = document.getElementById('c_code_postal').value.trim();
      const pays = document.getElementById('c_pays').value.trim();
      const adresse = document.getElementById('c_adresse').value.trim();
      const etage = document.getElementById('c_etage').value.trim();
      const code_entree = document.getElementById('c_code_entree').value.trim();
      const source_acquisition = document.getElementById('c_source_acquisition').value;
      const mode_paiement = document.getElementById('c_mode_paiement').value;
      const avance_imme = document.getElementById('c_avance_imme').checked ? 1 : 0;
      if (!prenom && !nom) return alert('Prénom ou nom requis');
      if (editingId){
        fetch('api/clients.php?action=update', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: editingId, nom, prenom, email, telephone_mobile, telephone_fixe, ville, code_postal, pays, adresse, etage, code_entree, source_acquisition, mode_paiement, avance_imme })})
          .then(r=>r.json()).then(j=>{ if (j.error) alert('Erreur: '+j.error); else { clearForm(); loadClients(document.getElementById('q').value); } })
          .catch(e=>{ console.error(e); alert('Erreur réseau') });
      } else {
        fetch('api/clients.php?action=create', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ nom, prenom, email, telephone_mobile, telephone_fixe, ville, code_postal, pays, adresse, etage, code_entree, source_acquisition, mode_paiement, avance_imme })})
          .then(r=>r.json()).then(j=>{ if (j.error) alert('Erreur: '+j.error); else { clearForm(); loadClients(document.getElementById('q').value); } })
          .catch(e=>{ console.error(e); alert('Erreur réseau') });
      }
    }

    function deleteClient(id){
      fetch('api/clients.php?action=delete', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: id })})
        .then(r=>r.json()).then(j=>{ if (j.error) alert('Erreur: '+j.error); else loadClients(document.getElementById('q').value); })
        .catch(e=>{ console.error(e); alert('Erreur réseau') });
    }

    document.addEventListener('DOMContentLoaded', function(){
      document.getElementById('btnSaveClient').addEventListener('click', saveClient);
      document.getElementById('btnCancelEdit').addEventListener('click', clearForm);
      document.getElementById('q').addEventListener('input', function(){ loadClients(this.value); });
      loadClients();
    });
  </script>
</body>
</html>
