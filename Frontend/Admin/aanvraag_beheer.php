<?php
session_start();
include '../../Backend/DatabaseContext/Database.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$usertype = $_SESSION['usertype'] ?? null;

// Filter op basis van querystring
$filter = $_GET['filter'] ?? 'alle';
$whereDate = '';

switch ($filter) {
    case 'vandaag':
        $whereDate = "AND DATE(a.datum) = CURDATE()";
        break;
    case 'gisteren':
        $whereDate = "AND DATE(a.datum) = CURDATE() - INTERVAL 1 DAY";
        break;
    case 'week':
        $whereDate = "AND DATE(a.datum) >= CURDATE() - INTERVAL 7 DAY";
        break;
    default:
        $whereDate = '';
}

$conn = Database::GetConnection();

if ($usertype == 'admin' && isset($_GET['id']) && isset($_GET['actie'])) {
    $id = $_GET['id'];
    $actie = $_GET['actie'];
    $status = ($actie == 'goedkeuren') ? 'goedgekeurd' : 'afgewezen';

    // Update aanvraagstatus
    $stmt = $conn->prepare("UPDATE aanvragen SET status = :status WHERE id = :id");
    $stmt->execute(['status' => $status, 'id' => $id]);

    // Als goedgekeurd: update complex_id in users-tabel
    if ($status == 'goedgekeurd') {
        $stmt = $conn->prepare("SELECT user_id, complex_id FROM aanvragen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $user_id = $row['user_id'];
            $complex_id = $row['complex_id'];
            $updateStmt = $conn->prepare("UPDATE users SET complex_id = :complex_id WHERE id = :user_id");
            $updateStmt->execute([
                'complex_id' => $complex_id,
                'user_id' => $user_id
            ]);
        }
    }

    header("Location: aanvragenbeheer.php");
    exit();
}

// Haal alle aanvragen op (met filters)
$sql = "SELECT a.id, u.email, u.name AS gebruiker_name, c1.name AS complex_name, c2.name AS tweede_name, 
               a.datum, a.status, a.opmerking 
        FROM aanvragen a
        JOIN users u ON a.user_id = u.id
        JOIN complexes c1 ON a.complex_id = c1.id
        LEFT JOIN complexes c2 ON a.tweede_keuze_id = c2.id
        WHERE 1 $whereDate
        ORDER BY a.datum DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Aanvragenbeheer - VTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1e1e1e; color: white; }
        .header { background-color: #7cb342; padding: 20px; text-align: center; font-size: 28px; font-weight: bold; }
        .content { padding: 40px; max-width: 95%; margin: auto; background-color: #2e2e2e; border-radius: 12px; }
        table { min-width: 1000px; width: 100%; border-collapse: collapse; background-color: #3e3e3e; }
        thead { background-color: #689f38; }
        th, td { padding: 12px; text-align: center; }
        tr:nth-child(even) { background-color: #444; }
        tr:hover { background-color: #505050; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        .terug-btn { display: block; margin: 30px auto 0; padding: 10px 25px; background-color: #7cb342; color: white; border-radius: 8px; text-decoration: none; }
        .terug-btn:hover { background-color: #689f38; }
        .status-nieuw { color: orange; font-weight: bold; }
        .status-goedgekeurd { color: #00e676; font-weight: bold; }
        .status-afgewezen { color: #ff5252; font-weight: bold; }
        .filterbar { text-align: center; margin-bottom: 20px; }
        .filterbar a { color: white; text-decoration: none; margin: 0 10px; font-weight: bold; }
        .filterbar a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">VOLKSTUIN VERENIGING SITTARD</div>
    <div class="content">
        <h3>Aanvragenbeheer</h3>

        <div class="filterbar">
            <span>Filter: </span>
            <a href="?filter=alle">Alle</a> |
            <a href="?filter=vandaag">Vandaag</a> |
            <a href="?filter=gisteren">Gisteren</a> |
            <a href="?filter=week">Afgelopen week</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Naam</th>
                    <th>Voorkeurscomplex</th>
                    <th>Tweede keuze</th>
                    <th>Datum</th>
                    <th>Motivatie</th>
                    <th>Status</th>
                    <?php if ($usertype == 'admin') echo "<th>Acties</th>"; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!$result) {
                    echo "<tr><td colspan='8' style='color: red; text-align: center;'>SQL-fout</td></tr>";
                } else {
                    foreach ($result as $row) {
                        $statusClass = 'status-' . strtolower($row['status']);
                        echo "<tr>
                            <td>{$row['email']}</td>
                            <td>" . ($row['gebruiker_name'] ?? '-') . "</td>
                            <td>{$row['complex_name']}</td>
                            <td>" . ($row['tweede_name'] ?? '-') . "</td>
                            <td>{$row['datum']}</td>
                            <td>" . (!empty($row['opmerking']) ? htmlspecialchars($row['opmerking']) : '-') . "</td>
                            <td class='$statusClass'>{$row['status']}</td>";
                        if ($usertype == 'admin') {
                            echo "<td>
                                <a href='?id={$row['id']}&actie=goedkeuren' class='btn btn-success btn-sm'>✔ Goedkeuren</a>
                                <a href='?id={$row['id']}&actie=afwijzen' class='btn btn-danger btn-sm'>✖ Afwijzen</a>
                            </td>";
                        }
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>
        <a href="dashboard.php" class="terug-btn">← Terug naar Dashboard</a>
    </div>
</body>
</html>
