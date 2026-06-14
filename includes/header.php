<!-- En-tête de navigation commun -->
<style>
.app-header {
    background: white;
    padding: 8px 0;
    margin-bottom: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.app-header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.app-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 24px;
    font-weight: 700;
    color: #667eea;
    text-decoration: none;
}

.app-nav {
    display: flex;
    gap: 10px;
    align-items: center;
}

.nav-link {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.nav-link.green {
    background: #4caf50;
    color: white;
}

.nav-link.green:hover {
    background: #45a049;
}

.nav-link.blue {
    background: #1976d2;
    color: white;
}

.nav-link.blue:hover {
    background: #1565c0;
}

.nav-link.orange {
    background: #ff9800;
    color: white;
}

.nav-link.orange:hover {
    background: #e68900;
}

.nav-link.purple {
    background: #9c27b0;
    color: white;
}

.nav-link.purple:hover {
    background: #7b1fa2;
}

.nav-link.teal {
    background: #00897b;
    color: white;
}

.nav-link.teal:hover {
    background: #00695c;
}

.nav-link.red {
    background: #e53935;
    color: white;
}

.nav-link.red:hover {
    background: #c62828;
}

.nav-link.indigo {
    background: #3f51b5;
    color: white;
}

.nav-link.indigo:hover {
    background: #303f9f;
}

.nav-link.gray {
    background: #607d8b;
    color: white;
}

.nav-link.gray:hover {
    background: #455a64;
}

@media (max-width: 768px) {
    .app-header-content {
        flex-direction: column;
        gap: 15px;
    }
    
    .app-nav {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .nav-link {
        font-size: 12px;
        padding: 6px 12px;
    }
}
</style>

<div class="app-header">
    <div class="app-header-content">
        <a href="agenda.php" class="app-logo">
            📅 <?php echo defined('APP_NAME') ? APP_NAME : 'Agenda Pro'; ?>
        </a>
        <nav class="app-nav">
            <a href="agenda.php" class="nav-link purple">📅 Agenda</a>
            <a href="techniciens.php" class="nav-link green">👨‍💼 Techniciens</a>
            <a href="clients.php" class="nav-link blue">👥 Clients</a>
            <a href="forfaits.php" class="nav-link orange">📦 Forfaits</a>
            <a href="vehicules.php" class="nav-link red">🚗 Véhicules</a>
            <a href="bareme_fiscal.php" class="nav-link gray">📊 Barèmes km</a>
            <a href="gestion.php" class="nav-link purple">💼 Gestion</a>
            <a href="statistiques.php" class="nav-link teal">📈 Statistiques</a>
            <a href="rentabilite.php" class="nav-link indigo">💰 Rentabilité</a>
        </nav>
    </div>
</div>
