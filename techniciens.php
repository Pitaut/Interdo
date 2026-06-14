<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion des techniciens - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="includes/common_styles.css">
    <style>
        body { padding: 0; }
        .btn-small { padding: 6px 12px; font-size: 13px; }
        .actions { display:flex; gap:6px; }
        .palette-grid { display:flex; flex-wrap:wrap; gap:4px; width:100%; max-height:260px; overflow:auto; align-items:flex-start; padding:6px 4px; box-sizing:border-box; border:1px solid #eee; border-radius:6px; background:#fafafa; }
        .palette-swatch { width:10px; height:10px; padding:0; margin:0; border:1px solid #ddd; border-radius:2px; cursor:pointer; box-sizing:border-box; }
        .palette-swatch-selected { outline:2px solid rgba(0,0,0,0.12); }
        .palette-swatch-disabled { opacity:0.28; pointer-events:none; filter:grayscale(60%); }
        
        /* Style modal moderne */
        .modal-close {
            background: transparent;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .modal-close:hover {
            background: #f5f5f5;
            color: #333;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e5e5;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e5e5;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <h1>Gestion des techniciens</h1>
        <button id="btnNew" class="btn btn-success">+ Nouveau technicien</button>

        <div class="section" style="margin-top: 20px;">
        <table id="tableTechs">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Ville</th>
                    <th>Couleur</th>
                    <th>Date entrée</th>
                    <th>Date sortie</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content" style="max-width:700px;">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nouveau technicien</h2>
                <button class="modal-close" onclick="closeModal()" title="Fermer">✕</button>
            </div>
            <div class="modal-body" id="form">
                <div class="form-row"><input placeholder="Nom *" id="nom" required /><input placeholder="Prénom *" id="prenom" required /></div>
                <div class="form-row"><input placeholder="Email" id="email" type="email" /><input placeholder="Téléphone mobile" id="telephone_mobile" /></div>
                <div class="form-row"><input placeholder="Ville" id="ville" /><input placeholder="Code Postal" id="code_postal" /></div>
                <div class="form-row">
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Date d'entrée</label>
                        <input type="date" id="date_entree" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" />
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Date de sortie</label>
                        <input type="date" id="date_sortie" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" />
                    </div>
                </div>
                <div class="form-row"><input placeholder="Téléphone fixe" id="telephone_fixe" /><input placeholder="Pays" id="pays" /></div>
                <div class="form-row"><textarea placeholder="Adresse" id="adresse" style="width:100%; height:70px;padding:8px;border:1px solid #ddd;border-radius:4px;"></textarea></div>
                <div class="form-row">
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">💰 Salaire horaire (€)</label>
                        <input type="number" step="0.01" placeholder="15.00" id="salaire_horaire" style="padding:8px;border:1px solid #ddd;border-radius:4px;" />
                    </div>
                    <div style="flex:1;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">
                            <input type="checkbox" id="actif" checked style="width:18px;height:18px;" /> 
                            Technicien actif
                        </label>
                    </div>
                </div>
                <div class="form-row" style="flex-direction:column;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">🎨 Couleur du technicien</label>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <input type="color" id="couleur" value="#667eea" style="width:60px; height:40px; padding:4px; border:1px solid #ddd;border-radius:4px;cursor:pointer;" />
                        <button id="togglePalette" class="btn-small" type="button" style="background:#f5f5f5;color:#333;border:1px solid #ddd;">📊 Nuancier 4096 couleurs</button>
                    </div>
                    <div id="paletteGrid" class="palette-grid" style="display:none;margin-top:12px;"></div>
                    <div id="fieldIndicator" style="margin-top:8px;padding:8px;background:#f9f9f9;border-radius:4px;font-size:12px;color:#666;">Champ actif: <span id="fieldName" style="font-weight:600;color:#333;">—</span></div>
                </div>
                
                <!-- Section véhicules (uniquement en mode édition) -->
                <div id="sectionVehicules" style="display:none; margin-top:20px; padding-top:20px; border-top:2px solid #eee;">
                    <h3 style="margin-bottom:15px; font-size:16px; color:#333;">🚗 Véhicules attribués</h3>
                    
                    <div style="margin-bottom:15px;">
                        <select id="vehiculeDisponible" style="padding:8px; border:1px solid #ddd; border-radius:4px; margin-right:10px;">
                            <option value="">-- Sélectionner un véhicule --</option>
                        </select>
                        <button id="btnAjouterVehicule" class="btn-small" style="background:#4caf50; color:white;">➕ Attribuer</button>
                    </div>
                    
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f5f5f5;">
                                <th style="padding:8px; text-align:left; border:1px solid #ddd;">Véhicule</th>
                                <th style="padding:8px; text-align:left; border:1px solid #ddd;">Immat.</th>
                                <th style="padding:8px; text-align:center; border:1px solid #ddd;">Principal</th>
                                <th style="padding:8px; text-align:center; border:1px solid #ddd;">Depuis</th>
                                <th style="padding:8px; text-align:center; border:1px solid #ddd;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="listeVehiculesAttribues">
                            <tr><td colspan="5" style="padding:20px; text-align:center; color:#999;">Aucun véhicule attribué</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button id="saveBtn" class="btn btn-success">✓ Enregistrer</button>
                <button id="cancelBtn" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
            </div>
        </div>
    </div>

    <script>
        async function loadTechs(){
            const resp = await fetch('api/techniciens.php?action=list');
            const data = await resp.json();
            // store globals for palette availability checks
            window._techniciensList = data;
            const tbody = document.querySelector('#tableTechs tbody');
            tbody.innerHTML = '';
            data.forEach(t => {
                const tr = document.createElement('tr');
                const color = t.couleur ? t.couleur : '#667eea';
                const dateEntree = t.date_entree ? new Date(t.date_entree).toLocaleDateString('fr-FR') : '—';
                const dateSortie = t.date_sortie ? new Date(t.date_sortie).toLocaleDateString('fr-FR') : '—';
                const hasComputedStatus = t.est_actif_periode !== undefined && t.est_actif_periode !== null;
                let isActivePeriod = hasComputedStatus ? !!t.est_actif_periode : true;
                let statutLabel = t.statut_label || '';

                if (!hasComputedStatus) {
                    const today = new Date();
                    const todayKey = today.toISOString().slice(0, 10);
                    const actifFlag = t.actif == 1 || t.actif === '1' || t.actif === true;
                    const beforeEntry = t.date_entree && t.date_entree > todayKey;
                    const afterExit = t.date_sortie && t.date_sortie <= todayKey;

                    isActivePeriod = actifFlag && !beforeEntry && !afterExit;
                    if (!statutLabel) {
                        if (beforeEntry) statutLabel = 'Pas encore entré';
                        else if (afterExit) statutLabel = `Sorti le ${new Date(t.date_sortie).toLocaleDateString('fr-FR')}`;
                        else if (!actifFlag) statutLabel = 'Désactivé';
                        else statutLabel = 'Actif';
                    }
                } else if (!statutLabel) {
                    statutLabel = isActivePeriod ? 'Actif' : 'Inactif';
                }

                const statutClass = isActivePeriod ? 'green' : 'red';
                const rowStyle = !isActivePeriod ? 'opacity:0.6;background:#f5f5f5;' : '';
                
                tr.style.cssText = rowStyle;
                tr.innerHTML = `<td>${t.id}</td><td>${escapeHtml(t.nom)}</td><td>${escapeHtml(t.prenom)}</td><td>${escapeHtml(t.email)}</td><td>${escapeHtml(t.ville)}</td><td><span style="display:inline-block;width:18px;height:18px;border-radius:4px;background:${escapeHtml(color)};border:1px solid #ccc;vertical-align:middle;"></span> ${escapeHtml(color)}</td><td>${dateEntree}</td><td>${dateSortie}</td><td><span style="color:${statutClass==='green'?'#4caf50':'#f44336'};font-weight:bold;">${escapeHtml(statutLabel)}</span></td><td class="actions"><button class="btn-small btn-edit" data-id="${t.id}">Edit</button><button class="btn-small btn-delete" data-id="${t.id}">Del</button></td>`;
                tbody.appendChild(tr);
            });
            document.querySelectorAll('.btn-delete').forEach(b=>b.addEventListener('click', onDelete));
            document.querySelectorAll('.btn-edit').forEach(b=>b.addEventListener('click', onEdit));
            // if palette exists, refresh availability (used colors may have changed)
            const grid = document.getElementById('paletteGrid');
            if (grid && grid._generated) {
                grid._usedColors = getUsedColorsSet();
                refreshPaletteAvailability();
            }
        }

        function escapeHtml(s){ if (!s) return ''; return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        let currentId = null;
        document.getElementById('btnNew').addEventListener('click', ()=>{ openModal(); });
        document.getElementById('cancelBtn').addEventListener('click', closeModal);
        document.getElementById('saveBtn').addEventListener('click', saveTech);
        // generate 4096-color palette (12-bit: 16 levels per channel)
        function toHex(v){
            return (v<16? '0':'') + v.toString(16).toUpperCase();
        }

        function getUsedColorsSet(){
            const set = new Set();
            if (window._techniciensList && Array.isArray(window._techniciensList)){
                window._techniciensList.forEach(t=>{
                    if (t && t.couleur) set.add(String(t.couleur).toUpperCase());
                });
            }
            return set;
        }

        function refreshPaletteAvailability(){
            const grid = document.getElementById('paletteGrid');
            if (!grid) return;
            const editingColor = (window._editingColor || '').toUpperCase();
            const used = grid._usedColors || getUsedColorsSet();
            grid.querySelectorAll('.palette-swatch').forEach(btn=>{
                const hex = (btn.dataset.color||'').toUpperCase();
                if (used.has(hex) && hex !== editingColor){
                    btn.classList.add('palette-swatch-disabled');
                    btn.disabled = true;
                    btn.title = hex + ' (déjà attribuée)';
                } else {
                    btn.classList.remove('palette-swatch-disabled');
                    btn.disabled = false;
                    btn.title = hex;
                }
            });
        }

        function rgbFrom4bit(n){
            // map 0..15 to 0..255 by multiply 17
            return n * 17;
        }

        function generatePaletteGrid(){
            const grid = document.getElementById('paletteGrid');
            if (!grid) return;
            // avoid regenerating
            if (grid._generated) return;
            grid._generated = true;
            // lazy-load configuration
            grid._total = 4096;
            grid._pageSize = 512; // number of swatches per block (tuneable)
            grid._generatedCount = 0;

            // compute used colors at generation time
            grid._usedColors = getUsedColorsSet();

            function indexToRGB(i){
                // i in [0..4095]
                const r = Math.floor(i / 256); // 16*16=256
                const g = Math.floor((i % 256) / 16);
                const b = i % 16;
                return [r,g,b];
            }

            grid._loadNext = function(){
                if (grid._generatedCount >= grid._total) return;
                const start = grid._generatedCount;
                const end = Math.min(grid._total, start + grid._pageSize);
                const frag = document.createDocumentFragment();
                for (let i = start; i < end; i++){
                    const [r,g,b] = indexToRGB(i);
                    const rr = rgbFrom4bit(r);
                    const gg = rgbFrom4bit(g);
                    const bb = rgbFrom4bit(b);
                    const hex = '#' + toHex(rr) + toHex(gg) + toHex(bb);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'palette-swatch';
                    btn.dataset.color = hex;
                    btn.style.background = hex;
                    // disable if already used by another technician (allow current editing color)
                    const editingColor = (window._editingColor || '').toUpperCase();
                    if (grid._usedColors && grid._usedColors.has(hex.toUpperCase()) && hex.toUpperCase() !== editingColor){
                        btn.classList.add('palette-swatch-disabled');
                        btn.disabled = true;
                        btn.title = hex + ' (déjà attribuée)';
                    } else {
                        btn.title = hex;
                    }
                    btn.addEventListener('click', (ev)=>{
                        const color = ev.currentTarget.dataset.color;
                        const inp = document.getElementById('couleur');
                        if (inp) inp.value = color;
                        // mark selected
                        document.querySelectorAll('.palette-swatch-selected').forEach(x=> x.classList.remove('palette-swatch-selected'));
                        ev.currentTarget.classList.add('palette-swatch-selected');
                    });
                    frag.appendChild(btn);
                }
                grid.appendChild(frag);
                grid._generatedCount = end;
            };

            // initial load
            grid._loadNext();

            // on scroll near bottom, load next block
            grid._onScroll = function(){
                const threshold = 160; // px from bottom to trigger
                if (grid.scrollHeight - grid.scrollTop - grid.clientHeight < threshold) {
                    grid._loadNext();
                    if (grid._generatedCount >= grid._total){
                        grid.removeEventListener('scroll', grid._onScroll);
                    }
                }
            };
            grid.addEventListener('scroll', grid._onScroll);
        }

        // toggle palette visibility
        const toggleBtn = document.getElementById('togglePalette');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                const grid = document.getElementById('paletteGrid');
                if (!grid) return;
                if (!grid._generated) generatePaletteGrid();
                if (grid.style.display === 'none' || grid.style.display === ''){
                    grid.style.display = 'flex';
                    toggleBtn.textContent = 'Masquer le nuancier';
                } else {
                    grid.style.display = 'none';
                    toggleBtn.textContent = 'Afficher le nuancier 4096 couleurs';
                }
            });
        }

        function openModal(data = null){
            currentId = data ? data.id : null;
            document.getElementById('modalTitle').textContent = data ? 'Modifier technicien' : 'Nouveau technicien';
            const keys = ['nom','prenom','email','telephone_mobile','telephone_fixe','ville','code_postal','pays','adresse','date_entree','date_sortie','couleur','salaire_horaire'];
            keys.forEach(k=>{
                const el = document.getElementById(k);
                if (!el) return;
                el.value = data && (data[k] !== undefined && data[k] !== null) ? data[k] : '';
            });
            // actif checkbox
            const actifEl = document.getElementById('actif');
            if (actifEl) {
                actifEl.checked = data ? (data.actif == 1 || data.actif === '1') : true;
            }
            
            // Afficher section véhicules uniquement en mode édition
            const sectionVehicules = document.getElementById('sectionVehicules');
            if (data && data.id) {
                sectionVehicules.style.display = 'block';
                chargerVehiculesTechnicien(data.id);
            } else {
                sectionVehicules.style.display = 'none';
            }
            
            document.getElementById('modal').style.display = 'flex';
            const fn = document.getElementById('fieldName'); if (fn) fn.textContent = '—';
            attachFocusIndicators();
        }
        function closeModal(){ document.getElementById('modal').style.display = 'none'; }

        async function onEdit(e){
            const id = e.currentTarget.dataset.id;
            const resp = await fetch('api/techniciens.php?action=list');
            const list = await resp.json();
            const row = list.find(x=>String(x.id)===String(id));
            openModal(row);
        }

        async function onDelete(e){
            if(!confirm('Confirmer suppression ?')) return;
            const id = e.currentTarget.dataset.id;
            const resp = await fetch('api/techniciens.php?action=delete',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:id})});
            const json = await resp.json();
            if (json.error) alert('Erreur: '+json.error); else loadTechs();
        }

        async function saveTech(){
            const payload = {};
            ['nom','prenom','email','telephone_mobile','telephone_fixe','ville','code_postal','pays','adresse','date_entree','date_sortie','couleur','salaire_horaire'].forEach(k=>{ 
                const el = document.getElementById(k);
                payload[k]= el ? el.value : null;
            });
            // actif
            const actifEl = document.getElementById('actif');
            payload.actif = actifEl ? (actifEl.checked ? 1 : 0) : 1;
            if (currentId) payload.id = currentId;
            const url = currentId ? 'api/techniciens.php?action=update' : 'api/techniciens.php?action=create';
            const resp = await fetch(url,{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
            const json = await resp.json();
            if (json.error) { alert('Erreur: '+json.error); } else { closeModal(); loadTechs(); }
        }

        function attachFocusIndicators(){
            const labels = {
                nom: 'Nom', prenom: 'Prénom', email: 'Email', telephone_mobile: 'Téléphone mobile', telephone_fixe: 'Téléphone fixe',
                ville: 'Ville', code_postal: 'Code postal', pays: 'Pays', adresse: 'Adresse', date_entree: 'Date d\'entrée', date_sortie: 'Date de sortie',
                couleur: 'Couleur', salaire_horaire: 'Salaire horaire', actif: 'Actif'
            };
            const selector = '#modal input, #modal textarea, #modal select';
            document.querySelectorAll(selector).forEach(el => {
                el.removeEventListener('focus', el._focusHandler);
                el.removeEventListener('blur', el._blurHandler);
                el._focusHandler = (ev) => {
                    const id = ev.currentTarget.id;
                    const fn = document.getElementById('fieldName'); if (fn) fn.textContent = labels[id] || id;
                };
                el._blurHandler = () => { setTimeout(()=>{ const fn = document.getElementById('fieldName'); if (fn) fn.textContent = '—'; }, 1500); };
                el.addEventListener('focus', el._focusHandler);
                el.addEventListener('blur', el._blurHandler);
            });
        }
        
        // ===== GESTION DES VÉHICULES =====
        async function chargerVehiculesTechnicien(id_technicien) {
            try {
                const resp = await fetch(`api/techniciens_vehicules.php?action=get_technicien_vehicules&id_technicien=${id_technicien}`);
                const data = await resp.json();
                
                // Remplir le select des véhicules disponibles
                const select = document.getElementById('vehiculeDisponible');
                select.innerHTML = '<option value="">-- Sélectionner un véhicule --</option>';
                data.disponibles.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = `${v.nom} - ${v.immatriculation}`;
                    select.appendChild(opt);
                });
                
                // Afficher la liste des véhicules attribués
                const tbody = document.getElementById('listeVehiculesAttribues');
                tbody.innerHTML = '';
                
                if (data.attribues.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding:20px; text-align:center; color:#999;">Aucun véhicule attribué</td></tr>';
                } else {
                    data.attribues.forEach(v => {
                        const tr = document.createElement('tr');
                        const principal = v.principal == 1;
                        tr.innerHTML = `
                            <td style="padding:8px; border:1px solid #ddd;">${escapeHtml(v.nom)}</td>
                            <td style="padding:8px; border:1px solid #ddd;">${escapeHtml(v.immatriculation)}</td>
                            <td style="padding:8px; border:1px solid #ddd; text-align:center;">
                                ${principal ? 
                                    '<span style="color:#4caf50; font-weight:bold;">⭐ Principal</span>' : 
                                    `<button onclick="setPrincipal(${v.id}, ${id_technicien})" class="btn-small" style="font-size:11px;">Définir principal</button>`
                                }
                            </td>
                            <td style="padding:8px; border:1px solid #ddd; text-align:center;">${v.date_debut || '-'}</td>
                            <td style="padding:8px; border:1px solid #ddd; text-align:center;">
                                <button onclick="retirerVehicule(${v.id}, ${id_technicien})" class="btn-small" style="background:#f44336; color:white; font-size:11px;">Retirer</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (err) {
                console.error('Erreur chargement véhicules:', err);
            }
        }
        
        // Attribuer un véhicule au technicien
        document.getElementById('btnAjouterVehicule').addEventListener('click', async () => {
            const id_vehicule = document.getElementById('vehiculeDisponible').value;
            if (!id_vehicule || !currentId) {
                alert('Veuillez sélectionner un véhicule');
                return;
            }
            
            try {
                const resp = await fetch('api/techniciens_vehicules.php?action=ajouter_vehicule', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id_technicien: currentId,
                        id_vehicule: parseInt(id_vehicule),
                        principal: false,
                        date_debut: new Date().toISOString().split('T')[0]
                    })
                });
                
                const data = await resp.json();
                if (data.error) {
                    alert('Erreur: ' + data.error);
                } else {
                    chargerVehiculesTechnicien(currentId);
                }
            } catch (err) {
                console.error('Erreur attribution véhicule:', err);
                alert('Erreur lors de l\'attribution du véhicule');
            }
        });
        
        // Retirer un véhicule
        async function retirerVehicule(id, id_technicien) {
            if (!confirm('Terminer l\'attribution de ce véhicule ?')) return;
            
            try {
                const resp = await fetch('api/techniciens_vehicules.php?action=retirer_vehicule', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: id,
                        date_fin: new Date().toISOString().split('T')[0]
                    })
                });
                
                const data = await resp.json();
                if (data.error) {
                    alert('Erreur: ' + data.error);
                } else {
                    chargerVehiculesTechnicien(id_technicien);
                }
            } catch (err) {
                console.error('Erreur retrait véhicule:', err);
                alert('Erreur lors du retrait du véhicule');
            }
        }
        
        // Définir un véhicule comme principal
        async function setPrincipal(id, id_technicien) {
            try {
                const resp = await fetch('api/techniciens_vehicules.php?action=set_principal', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: id,
                        id_technicien: id_technicien
                    })
                });
                
                const data = await resp.json();
                if (data.error) {
                    alert('Erreur: ' + data.error);
                } else {
                    chargerVehiculesTechnicien(id_technicien);
                }
            } catch (err) {
                console.error('Erreur définition véhicule principal:', err);
                alert('Erreur lors de la définition du véhicule principal');
            }
        }

        loadTechs();
    </script>
</body>
</html>
