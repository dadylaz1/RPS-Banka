<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function database(): PDO
{
    $host = getenv('BENKOBANKA_DB_HOST');
    $name = getenv('BENKOBANKA_DB_NAME');
    $user = getenv('BENKOBANKA_DB_USER');
    $password = getenv('BENKOBANKA_DB_PASSWORD');

    if (!$host || !$name || $user === false || $password === false) {
        throw new RuntimeException('Database environment variables are not configured.');
    }

    return new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function payload(): array
{
    try {
        $decoded = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        respond(400, ['error' => 'Neveljavna zahteva.']);
    }

    if (!is_array($decoded)) {
        respond(400, ['error' => 'Neveljavna zahteva.']);
    }

    return $decoded;
}

function requiredText(array $data, string $key, int $maxCharacters): string
{
    if (!isset($data[$key]) || !is_string($data[$key])) {
        respond(422, ['error' => 'Preverite obvezna polja in njihovo dolžino.']);
    }

    $value = trim($data[$key]);
    if ($value === '' || mb_strlen($value, 'UTF-8') > $maxCharacters) {
        respond(422, ['error' => 'Preverite obvezna polja in njihovo dolžino.']);
    }
    return $value;
}

function optionalText(array $data, string $key, int $maxCharacters): string
{
    if (!isset($data[$key])) {
        return '';
    }
    if (!is_string($data[$key])) {
        respond(422, ['error' => 'Preverite vnesene podatke.']);
    }

    $value = trim($data[$key]);
    if (mb_strlen($value, 'UTF-8') > $maxCharacters) {
        respond(422, ['error' => 'Preverite vnesene podatke.']);
    }
    return $value;
}

function requiredSecret(array $data, string $key): string
{
    if (!isset($data[$key]) || !is_string($data[$key])
        || $data[$key] === '' || strlen($data[$key]) > 4096) {
        respond(422, ['error' => 'Geslo ni veljavno.']);
    }
    return $data[$key];
}

function optionalSecret(array $data, string $key): string
{
    if (!isset($data[$key])) {
        return '';
    }
    if (!is_string($data[$key]) || strlen($data[$key]) > 4096) {
        respond(422, ['error' => 'Geslo ni veljavno.']);
    }
    return $data[$key];
}

function validDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function requireCsrf(array $data): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
        respond(403, ['error' => 'Seja je potekla. Osvežite stran in poskusite znova.']);
    }
}

function requireCustomerId(): int
{
    $id = $_SESSION['customer_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        respond(401, ['error' => 'Za nadaljevanje se prijavite.']);
    }
    return $id;
}

try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && $action === 'account') {
        $customerId = requireCustomerId();
        $customerQuery = $db->prepare(
            'SELECT ID_stranke, Ime, Priimek, E_posta, Telefon, Datum_rojstva
             FROM Stranka WHERE ID_stranke = ?'
        );
        $customerQuery->execute([$customerId]);
        $customer = $customerQuery->fetch();
        if (!$customer) {
            session_destroy();
            respond(401, ['error' => 'Račun ni več na voljo.']);
        }

        $accountsQuery = $db->prepare(
            'SELECT ID_racuna, St_racuna, Stanje, Tip_racuna
             FROM Racun WHERE ID_stranke = ? ORDER BY ID_racuna'
        );
        $accountsQuery->execute([$customerId]);
        $creditsQuery = $db->prepare(
            'SELECT Znesek, Doba_let, Obrestna_mera, Mesecni_obrok
             FROM Kredit WHERE ID_stranke = ? ORDER BY ID_kredita'
        );
        $creditsQuery->execute([$customerId]);

        respond(200, [
            'customer' => $customer,
            'accounts' => $accountsQuery->fetchAll(),
            'credits' => $creditsQuery->fetchAll(),
            'csrfToken' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    if ($method !== 'POST') {
        respond(405, ['error' => 'Metoda ni podprta.']);
    }

    $data = payload();

    if ($action === 'register') {
        if (($data['pogoji'] ?? false) !== true) {
            respond(422, ['error' => 'Za registracijo se morate strinjati s splošnimi pogoji.']);
        }

        $firstName = requiredText($data, 'ime', 50);
        $lastName = requiredText($data, 'priimek', 50);
        $email = strtolower(requiredText($data, 'eposta', 100));
        $phone = optionalText($data, 'telefon', 30);
        $birthDate = requiredText($data, 'datum_rojstva', 10);
        $password = requiredSecret($data, 'geslo');
        $repeatedPassword = requiredSecret($data, 'ponovitev_gesla');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(422, ['error' => 'Vnesite veljaven e-poštni naslov.']);
        }
        if (!validDate($birthDate) || mb_strlen($password, 'UTF-8') < 8 || $password !== $repeatedPassword) {
            respond(422, ['error' => 'Preverite datum rojstva in geslo (najmanj 8 znakov).']);
        }

        $exists = $db->prepare('SELECT ID_stranke FROM Stranka WHERE E_posta = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            respond(409, ['error' => 'Račun s tem e-poštnim naslovom že obstaja.']);
        }

        $db->beginTransaction();
        $insertCustomer = $db->prepare(
            'INSERT INTO Stranka (Ime, Priimek, E_posta, Telefon, Datum_rojstva, Geslo)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertCustomer->execute([
            $firstName,
            $lastName,
            $email,
            $phone !== '' ? $phone : null,
            $birthDate,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $customerId = (int)$db->lastInsertId();

        $accountNumber = 'SI56' . random_int(1000000000000000, 9999999999999999);
        $insertAccount = $db->prepare(
            'INSERT INTO Racun (ID_stranke, St_racuna, Stanje, Tip_racuna) VALUES (?, ?, 0, ?)'
        );
        $insertAccount->execute([$customerId, $accountNumber, 'Osebni račun']);
        $db->commit();

        session_regenerate_id(true);
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(201, ['csrfToken' => $_SESSION['csrf_token']]);
    }

    if ($action === 'login') {
        $email = strtolower(requiredText($data, 'eposta', 100));
        $password = isset($data['geslo']) && is_string($data['geslo']) ? $data['geslo'] : '';
        $query = $db->prepare('SELECT ID_stranke, Geslo FROM Stranka WHERE E_posta = ?');
        $query->execute([$email]);
        $customer = $query->fetch();

        if (!$customer || !password_verify($password, $customer['Geslo'])) {
            respond(401, ['error' => 'E-poštni naslov ali geslo ni pravilno.']);
        }

        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$customer['ID_stranke'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(200, ['csrfToken' => $_SESSION['csrf_token']]);
    }

    if ($action === 'update') {
        requireCsrf($data);
        $customerId = requireCustomerId();
        $firstName = requiredText($data, 'ime', 50);
        $lastName = requiredText($data, 'priimek', 50);
        $email = strtolower(requiredText($data, 'eposta', 100));
        $phone = optionalText($data, 'telefon', 30);
        $birthDate = requiredText($data, 'datum_rojstva', 10);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)
            || !validDate($birthDate)) {
            respond(422, ['error' => 'Preverite vnesene osebne podatke.']);
        }

        $duplicate = $db->prepare('SELECT ID_stranke FROM Stranka WHERE E_posta = ? AND ID_stranke <> ?');
        $duplicate->execute([$email, $customerId]);
        if ($duplicate->fetch()) {
            respond(409, ['error' => 'Ta e-poštni naslov že uporablja drug račun.']);
        }

        $currentPassword = optionalSecret($data, 'staro_geslo');
        $newPassword = optionalSecret($data, 'novo_geslo');
        $repeatPassword = optionalSecret($data, 'ponovi_novo_geslo');
        $passwordChangeRequested = $currentPassword !== '' || $newPassword !== '' || $repeatPassword !== '';
        $passwordHash = null;

        if ($passwordChangeRequested) {
            $passwordQuery = $db->prepare('SELECT Geslo FROM Stranka WHERE ID_stranke = ?');
            $passwordQuery->execute([$customerId]);
            $storedPassword = $passwordQuery->fetchColumn();
            if (!is_string($storedPassword) || !password_verify($currentPassword, $storedPassword)) {
                respond(422, ['error' => 'Trenutno geslo ni pravilno.']);
            }
            if (mb_strlen($newPassword, 'UTF-8') < 8 || $newPassword !== $repeatPassword) {
                respond(422, ['error' => 'Novo geslo mora imeti vsaj 8 znakov in se mora ujemati s ponovitvijo.']);
            }
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if ($passwordHash !== null) {
            $update = $db->prepare(
                'UPDATE Stranka SET Ime = ?, Priimek = ?, E_posta = ?, Telefon = ?, Datum_rojstva = ?, Geslo = ?
                 WHERE ID_stranke = ?'
            );
            $update->execute([$firstName, $lastName, $email, $phone !== '' ? $phone : null, $birthDate, $passwordHash, $customerId]);
        } else {
            $update = $db->prepare(
                'UPDATE Stranka SET Ime = ?, Priimek = ?, E_posta = ?, Telefon = ?, Datum_rojstva = ?
                 WHERE ID_stranke = ?'
            );
            $update->execute([$firstName, $lastName, $email, $phone !== '' ? $phone : null, $birthDate, $customerId]);
        }

        respond(200, ['message' => 'Nastavitve so shranjene.']);
    }

    if ($action === 'logout') {
        requireCsrf($data);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(200, ['message' => 'Odjavljeni ste.']);
    }

    respond(404, ['error' => 'Zahtevana storitev ne obstaja.']);
} catch (PDOException $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($exception->getCode() === '23000') {
        respond(409, ['error' => 'E-poštni naslov ali številka računa že obstaja.']);
    }
    error_log('Benko banka database error: ' . $exception->getMessage());
    respond(500, ['error' => 'Povezava z zbirko podatkov ni uspela. Preverite nastavitev strežnika.']);
} catch (RuntimeException $exception) {
    error_log('Benko banka configuration error: ' . $exception->getMessage());
    respond(500, ['error' => 'Strežnik zbirke podatkov še ni nastavljen.']);
}
