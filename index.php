<?php
/**
 * =====================================================================
 *  Aquarium Shop Ultimate Edition — รวม 5 เวอร์ชัน (หน้าร้าน + สมาชิก + หลังร้านเต็มรูปแบบ)
 *  วางไฟล์ที่ : C:\xampp\htdocs\aquarium shop\index.php
 *  หน้าร้าน   : http://localhost/aquarium%20shop/
 *  หลังร้าน   : http://localhost/aquarium%20shop/?page=admin   (admin / admin123)
 * =====================================================================
 */
if (PHP_VERSION_ID < 70400) die('ต้องใช้ PHP 7.4 ขึ้นไป (แนะนำ XAMPP ที่มี PHP 8)');
session_start();
date_default_timezone_set('Asia/Bangkok');

/* ============================ ตั้งค่าระบบ ============================ */
const DB_HOST       = 'localhost';
const DB_USER       = 'root';
const DB_PASS       = '';
const DB_NAME       = 'aquarium shop';
const SHIP_FEE      = 60;
const FREE_SHIP_MIN = 1000;
const BANK_INFO     = 'ธนาคารกสิกรไทย เลขที่ 123-4-56789-0 ชื่อบัญชี Aquarium Shop';
const ORDER_STATUS  = ['pending' => 'รอตรวจสอบสลิป', 'paid' => 'ชำระเงินแล้ว', 'shipped' => 'จัดส่งแล้ว', 'completed' => 'สำเร็จ', 'cancelled' => 'ยกเลิก'];
const ART_ICON      = ['guppy' => '🐟', 'goldfish' => '🐠', 'balloon' => '🐡', 'plant' => '🌿', 'food' => '🍤', 'decor' => '🏰', 'clean' => '🧽'];
const ART_LABEL     = ['guppy' => 'ปลาหางนกยูง', 'goldfish' => 'ปลาทอง', 'balloon' => 'ปลาบอลลูน', 'plant' => 'ไม้น้ำ', 'food' => 'อาหาร', 'decor' => 'ของตกแต่ง', 'clean' => 'อุปกรณ์'];
const TABLES        = ['categories', 'products', 'admins', 'customers', 'orders', 'order_items'];
define('UPLOAD_DIR', __DIR__ . '/uploads/');

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);

/* ============================ เชื่อมต่อ + ติดตั้งอัตโนมัติ ============================ */
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $ok = true;
    foreach (TABLES as $t) if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetch()) $ok = false;
    if ($ok && !$pdo->query("SHOW COLUMNS FROM orders LIKE 'customer_id'")->fetch()) $ok = false;
    if (!$ok) { drop_tables($pdo); install($pdo); }

    if ((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) create_admin($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    die('<!DOCTYPE html><html lang="th"><meta charset="UTF-8"><body style="font-family:Tahoma;max-width:640px;margin:60px auto;line-height:1.7">'
      . '<h2>⚠️ ติดตั้งระบบไม่สำเร็จ</h2><ol>'
      . '<li>เปิด XAMPP Control Panel แล้วกด <b>Start</b> ที่ <b>MySQL</b></li>'
      . '<li>ถ้าตั้งรหัสผ่าน MySQL ไว้ ให้แก้ค่า <code>DB_PASS</code> ด้านบนของไฟล์ index.php</li>'
      . '<li>รีเฟรชหน้านี้อีกครั้ง</li></ol>'
      . '<pre style="background:#fee;padding:10px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre></body></html>');
}

function drop_tables(PDO $pdo) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('DROP TABLE IF EXISTS order_items, orders, customers, products, categories, admins');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function create_admin(PDO $pdo) {
    $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)')
        ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
}

function install(PDO $pdo) {
    $opt = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec("CREATE TABLE categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        art VARCHAR(20) NOT NULL DEFAULT 'guppy',
        sort_order INT NOT NULL DEFAULT 0
    ) $opt");
    $pdo->exec("CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        size VARCHAR(100) NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock INT NOT NULL DEFAULT 0,
        color1 CHAR(7) NOT NULL DEFAULT '#ff7043',
        color2 CHAR(7) NOT NULL DEFAULT '#ffca28',
        image VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    ) $opt");
    $pdo->exec("CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $opt");
    $pdo->exec("CREATE TABLE customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $opt");
    $pdo->exec("CREATE TABLE orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        customer_name VARCHAR(150) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        note TEXT,
        payment_method ENUM('transfer','cod') NOT NULL DEFAULT 'transfer',
        slip_image VARCHAR(255) NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
        total DECIMAL(10,2) NOT NULL,
        status ENUM('pending','paid','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    ) $opt");
    $pdo->exec("CREATE TABLE order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        qty INT NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) $opt");

    $cats = [1 => ['ปลาหางนกยูง', 'guppy'], 2 => ['ปลาทอง', 'goldfish'], 3 => ['ปลาบอลลูน', 'balloon'], 4 => ['ไม้น้ำ', 'plant'],
             5 => ['อาหารปลา', 'food'], 6 => ['ของตกแต่งตู้ปลา', 'decor'], 7 => ['อุปกรณ์ทำความสะอาดตู้ปลา', 'clean']];
    $st = $pdo->prepare('INSERT INTO categories (id, name, art, sort_order) VALUES (?,?,?,?)');
    foreach ($cats as $id => [$n, $a]) $st->execute([$id, $n, $a, $id]);

    $G = 'คู่ (ผู้+เมีย)'; $T = '3 นิ้ว'; $B = '1.5 นิ้ว';
    $rows = [
        [1,'หางนกยูง Full Red',$G,'สีแดงสดทั้งตัว หางใหญ่ เลี้ยงง่าย',150,30,'#e53935','#c62828'],
        [1,'หางนกยูง Full Black',$G,'ลำตัวและครีบสีดำสนิท ดูหรูหรา',180,20,'#37474f','#111111'],
        [1,'หางนกยูง Albino Full Red',$G,'ตาแดงอัลบิโน ตัวสีแดงทั้งตัว',220,15,'#ff8a80','#e53935'],
        [1,'หางนกยูง Blue Grass',$G,'หางลายจุดแบบหญ้า โทนฟ้า',120,30,'#90a4ae','#1e88e5'],
        [1,'หางนกยูง Red Grass',$G,'หางลายจุดแบบหญ้า โทนแดง',120,30,'#b0bec5','#e53935'],
        [1,'หางนกยูง Moscow Blue',$G,'สีน้ำเงินเข้มเงางามทั้งตัว',200,18,'#283593','#1a237e'],
        [1,'หางนกยูง Moscow Green',$G,'สีเขียวเหลือบโลหะ หายาก',220,12,'#2e7d32','#1b5e20'],
        [1,'หางนกยูง Dumbo Ear Red (หูช้าง)',$G,'ครีบอกใหญ่เหมือนหูช้าง หางแดง',250,12,'#eceff1','#d50000'],
        [1,'หางนกยูง Koi',$G,'ลวดลายแดงขาวคล้ายปลาคาร์ป',180,20,'#fafafa','#ff3d00'],
        [1,'หางนกยูง Platinum White',$G,'สีขาวแพลทินัมเงาวาว',200,15,'#eceff1','#ffffff'],
        [1,'หางนกยูง Cobra Green',$G,'ลายงูเห่าบนลำตัว โทนเขียว',100,25,'#558b2f','#9ccc65'],
        [1,'หางนกยูง Tuxedo Blue',$G,'ครึ่งตัวหลังสีเข้มเหมือนใส่ทักซิโด้',90,35,'#4fc3f7','#0d47a1'],
        [1,'หางนกยูง Japan Blue',$G,'ลำตัวสีฟ้าสะท้อนแสง',150,20,'#29b6f6','#ff7043'],
        [1,'หางนกยูง Purple Mosaic',$G,'หางลายโมเสกโทนม่วง',160,18,'#b39ddb','#6a1b9a'],
        [1,'หางนกยูง Half Black Yellow',$G,'ครึ่งตัวดำ หางเหลืองสด',140,20,'#212121','#fdd835'],
        [2,'ปลาทองออแรนดา (Oranda)',$T,'วุ้นบนหัวสวย หางพลิ้ว',150,20,'#ff7043','#ffab91'],
        [2,'ปลาทองหัวแดง (Red Cap Oranda)',$T,'ตัวขาว วุ้นหัวสีแดง',250,12,'#fafafa','#e53935'],
        [2,'ปลาทองริวกิ้น (Ryukin)',$T,'หลังโค้งสูง หางยาว',180,18,'#ff5722','#ffccbc'],
        [2,'ปลาทองรันชู (Ranchu)',$T,'ไม่มีครีบหลัง ทรงตัวกลม',350,10,'#ff6f00','#ffe0b2'],
        [2,'ปลาทองสิงห์ (Lionhead)',$T,'วุ้นหัวเต็ม คล้ายแผงคอสิงโต',300,10,'#f4511e','#ffab91'],
        [2,'ปลาทองมุก (Pearlscale)',$T,'เกล็ดนูนเหมือนเม็ดมุก ตัวกลม',200,15,'#ffa726','#fff3e0'],
        [2,'ปลาทองตาโปนดำ (Black Moor)',$T,'ตาโปน ตัวสีดำกำมะหยี่',120,20,'#212121','#424242'],
        [2,'ปลาทองตาลูกโป่ง (Bubble Eye)',$T,'มีถุงน้ำใต้ตา ควรเลี้ยงในตู้ไม่มีของแหลม',250,8,'#ffb74d','#ffe0b2'],
        [2,'ปลาทองตากลับ (Celestial Eye)',$T,'ตาหงายมองขึ้นฟ้า',280,8,'#ff9800','#ffe0b2'],
        [2,'ปลาทองชูบุงกิ้น (Shubunkin)',$T,'ลายสามสี ว่ายเร็ว เลี้ยงบ่อได้',80,25,'#90caf9','#ff7043'],
        [2,'ปลาทองโคเมท (Comet)',$T,'ทรงยาว หางแฉกยาว แข็งแรง',60,30,'#ff6d00','#ffab40'],
        [2,'ปลาทองหางพัด (Fantail)',$T,'หางคู่บานเหมือนพัด เหมาะกับมือใหม่',90,25,'#ff7043','#ffcc80'],
        [2,'ปลาทองโทสะกิน (Tosakin)',$T,'หางบานแผ่กว้างแบบญี่ปุ่น หายาก',600,5,'#e64a19','#ff8a65'],
        [2,'ปลาทองหางผีเสื้อ (Butterfly Tail)',$T,'มองจากด้านบนหางเหมือนปีกผีเสื้อ',280,10,'#fafafa','#212121'],
        [2,'ปลาทองปอมปอม (Pompom)',$T,'มีพู่ที่จมูกเหมือนปอมปอม',220,10,'#ff8a65','#f06292'],
        [3,'บอลลูนขาว (White Balloon)',$B,'ตัวกลมสีขาวล้วน',40,40,'#fafafa','#e0e0e0'],
        [3,'บอลลูนดำ (Black Balloon)',$B,'ตัวกลมสีดำล้วน ช่วยกินตะไคร่',40,40,'#212121','#424242'],
        [3,'บอลลูนดัลเมเชียน (Dalmatian)',$B,'ลายจุดขาวดำแบบสุนัขดัลเมเชียน',50,30,'#fafafa','#212121'],
        [3,'บอลลูนทอง (Gold Balloon)',$B,'สีเหลืองทอง',45,30,'#ffd54f','#ffb300'],
        [3,'บอลลูนส้ม (Orange Balloon)',$B,'สีส้มสด',45,30,'#ff9800','#ffb74d'],
        [3,'บอลลูนแพลทินัม (Platinum)',$B,'เกล็ดเงาวาวแพลทินัม',60,20,'#e0e0e0','#b0bec5'],
        [3,'บอลลูนเงิน (Silver Balloon)',$B,'สีเงินเรียบหรู',45,25,'#cfd8dc','#90a4ae'],
        [3,'บอลลูนแพนด้า (Panda)',$B,'ลายขาวดำคล้ายแพนด้า',60,20,'#fafafa','#212121'],
        [3,'บอลลูนมาร์เบิล (Marble)',$B,'ลายหินอ่อนขาวดำ',55,25,'#9e9e9e','#212121'],
        [3,'บอลลูนดำหางพิณ (Lyretail Black)',$B,'หางแฉกสองปลายแบบพิณ',55,20,'#212121','#37474f'],
        [3,'บอลลูนเรือใบทอง (Sailfin Gold)',$B,'ครีบหลังสูงใหญ่แบบเรือใบ',70,15,'#ffca28','#ff8f00'],
        [3,'บอลลูนโค่ย (Koi Balloon)',$B,'ลายแดงขาวแบบปลาคาร์ป',65,15,'#fafafa','#ff3d00'],
        [4,'อนูเบียส นานา (Anubias Nana)','ติดขอนไม้','โตช้า ทนทาน ไม่ต้องใช้ CO2',120,20,'#2e7d32','#1b5e20'],
        [4,'เฟินชวา (Java Fern)','1 กอ','ปลูกติดหินหรือขอนไม้ เลี้ยงง่าย',90,20,'#388e3c','#66bb6a'],
        [4,'มอสชวา (Java Moss)','ถ้วย 5 ซม.','เหมาะทำพรม/ติดขอน และให้ลูกปลาหลบ',60,30,'#558b2f','#7cb342'],
        [4,'อเมซอนซอร์ด (Amazon Sword)','1 กระถาง','ใบใหญ่ เหมาะวางฉากหลัง',80,20,'#43a047','#2e7d32'],
        [4,'คริปโตคอรีน เวนดิไอ (Cryptocoryne Wendtii)','1 กระถาง','ใบสีน้ำตาลเขียว แสงน้อยก็โตได้',70,20,'#6d4c41','#558b2f'],
        [4,'สาหร่ายใบพาย (Vallisneria)','ชุด 5 ต้น','ใบยาวพลิ้ว เหมาะทำฉากหลัง',50,30,'#7cb342','#9ccc65'],
        [4,'สาหร่ายหางกระรอก (Hornwort)','กำ 10 ยอด','โตเร็ว ช่วยดูดซับของเสีย',40,40,'#33691e','#558b2f'],
        [4,'โรทาล่า (Rotala Rotundifolia)','กำ 10 ยอด','ยอดอมชมพูเมื่อได้แสงแรง',60,25,'#66bb6a','#f06292'],
        [4,'ลัดวิเจีย (Ludwigia Repens)','กำ 10 ยอด','ใบเขียวอมแดง ปลูกง่าย',60,25,'#689f38','#d84315'],
        [4,'มอนเตคาร์โล (Monte Carlo)','ถ้วยเนื้อเยื่อ','ไม้หน้าตู้ทำพรมเขียว',120,15,'#76ff03','#64dd17'],
        [5,'อาหารเม็ดลอยสูตรเร่งสี','50 g','เม็ดเล็ก เร่งสีแดงและสีสด เหมาะกับปลาทองและปลาหางนกยูง',45,50,'#e53935','#ffeb3b'],
        [5,'อาหารเม็ดลอยสูตรเร่งสี','100 g','เม็ดเล็ก เร่งสีแดงและสีสด เหมาะกับปลาทองและปลาหางนกยูง',79,40,'#e53935','#ffeb3b'],
        [5,'อาหารเม็ดลอยสูตรเร่งสี','500 g','ขนาดประหยัดสำหรับฟาร์ม/บ่อ',290,20,'#e53935','#ffeb3b'],
        [5,'อาหารแผ่น (Tropical Flakes)','20 g','อาหารแผ่นบาง ย่อยง่าย เหมาะกับปลาเล็ก',59,50,'#1e88e5','#ffffff'],
        [5,'อาหารแผ่น (Tropical Flakes)','50 g','อาหารแผ่นบาง ย่อยง่าย เหมาะกับปลาเล็ก',119,40,'#1e88e5','#ffffff'],
        [5,'อาหารแผ่น (Tropical Flakes)','200 g','ขนาดประหยัด',349,15,'#1e88e5','#ffffff'],
        [5,'ไรทะเลอบแห้ง (Freeze-dried Brine Shrimp)','10 g','โปรตีนสูง ช่วยเร่งโต',69,40,'#ff7043','#fff3e0'],
        [5,'ไรทะเลอบแห้ง (Freeze-dried Brine Shrimp)','25 g','โปรตีนสูง ช่วยเร่งโต',149,30,'#ff7043','#fff3e0'],
        [5,'ไรทะเลอบแห้ง (Freeze-dried Brine Shrimp)','100 g','ขนาดประหยัด',450,10,'#ff7043','#fff3e0'],
        [6,'ขอนไม้ธรรมชาติ (Driftwood)','M 20-25 ซม.','ผ่านการต้มฆ่าเชื้อแล้ว',250,15,'#6d4c41','#8d6e63'],
        [6,'หินเซเรียว (Seiryu Stone)','ชุด 3 ก้อน','หินภูเขาโทนเทา สำหรับจัดอะควาสเคป',350,10,'#78909c','#546e7a'],
        [6,'ทรายแม่น้ำล้างแล้ว','ถุง 5 กก.','สีธรรมชาติ ใช้รองพื้นตู้',120,30,'#d7ccc8','#bcaaa4'],
        [6,'ปราสาทเรซินจำลอง','ขนาดกลาง','ปลาว่ายลอดได้ ไม่มีขอบคม',290,10,'#bcaaa4','#5d4037'],
        [6,'ถ้ำหินเรซินให้ปลาหลบ','S','ให้ปลาหลบซ่อน ลดความเครียด',150,15,'#8d6e63','#3e2723'],
        [6,'ต้นไม้ปลอมซิลิโคน','ชุด 3 ต้น','นิ่ม ไม่ทำร้ายครีบปลา',99,25,'#43a047','#e91e63'],
        [6,'ฉากหลังตู้ลายใต้น้ำ','ยาว 60 ซม.','ติดหลังตู้ด้านนอก',180,15,'#0277bd','#4caf50'],
        [6,'หินกรวดสี','ถุง 1 กก.','หลากสี ล้างก่อนใช้',60,30,'#e91e63','#3f51b5'],
        [7,'สายกาลักน้ำดูดขี้ปลา','ยาว 1.5 ม.','ดูดตะกอนพื้นตู้พร้อมเปลี่ยนน้ำ',120,20,'#0288d1','#b3e5fc'],
        [7,'แปรงแม่เหล็กขัดตะไคร่','M (กระจก 8 มม.)','ขัดกระจกจากด้านนอกได้ ไม่ต้องเอามือลงน้ำ',190,15,'#e53935','#ffcdd2'],
        [7,'ใบมีดขูดตะไคร่ด้ามยาว','ด้าม 60 ซม.','ขูดคราบแข็งบนกระจก',150,15,'#455a64','#cfd8dc'],
        [7,'สวิงช้อนปลา','4 นิ้ว','ตาข่ายนิ่ม ไม่ทำร้ายปลา',35,40,'#2e7d32','#c8e6c9'],
        [7,'ใยกรองหยาบ','แผ่น 50x100 ซม.','ตัดใช้ในกรองได้ตามขนาด',80,25,'#1565c0','#e3f2fd'],
        [7,'น้ำยาปรับสภาพน้ำ/กำจัดคลอรีน','250 ml','ทำให้น้ำประปาปลอดภัยต่อปลา',89,30,'#00897b','#e0f2f1'],
        [7,'จุลินทรีย์น้ำใส','500 ml','ย่อยของเสีย ลดกลิ่น ช่วยให้น้ำใส',159,20,'#7b1fa2','#f3e5f5'],
        [7,'ฟองน้ำกรองสำรอง','1 ชิ้น','ใช้กับกรองฟองน้ำทั่วไป',60,30,'#f9a825','#fff9c4'],
    ];
    $st = $pdo->prepare('INSERT INTO products (category_id, name, size, description, price, stock, color1, color2) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($rows as $r) $st->execute($r);
    create_admin($pdo);
}

/* ============================ ฟังก์ชันช่วย ============================ */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n) { return '฿' . number_format((float)$n, 2); }
function url($page = 'home', $q = []) { return '?' . http_build_query(['page' => $page] + $q); }
function go($page = 'home', $q = []) { header('Location: ' . url($page, $q)); exit; }
function cart_count() { return array_sum($_SESSION['cart'] ?? []); }
function shipping_fee($sub) { return $sub >= FREE_SHIP_MIN ? 0 : SHIP_FEE; }
function art_icon($art) { return ART_ICON[$art] ?? '🐟'; }
function flash($msg, $type = 'success') { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function show_flash() {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    return '<div class="alert ' . ($f['type'] === 'error' ? 'error' : '') . '">' . e($f['msg']) . '</div>';
}
function status_badge($s) { return '<span class="badge st-' . e($s) . '">' . e(ORDER_STATUS[$s] ?? $s) . '</span>'; }
function valid_color($c, $default) { return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? $c : $default; }

function cart_items(PDO $pdo) {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) return [[], 0];
    $ids = array_keys($cart);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT p.*, c.art FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id IN ($in)");
    $st->execute($ids);
    $items = []; $total = 0;
    foreach ($st->fetchAll() as $p) {
        $p['qty'] = $cart[$p['id']];
        $p['subtotal'] = $p['qty'] * $p['price'];
        $total += $p['subtotal'];
        $items[] = $p;
    }
    return [$items, $total];
}

/* ===================== รูปสินค้า ===================== */
function product_img($p) {
    if (!empty($p['image']) && is_file(UPLOAD_DIR . $p['image'])) {
        return '<img src="uploads/' . e($p['image']) . '" alt="' . e($p['name']) . '">';
    }
    return art_svg($p['art'] ?? 'guppy', $p['color1'] ?? '', $p['color2'] ?? '');
}

function art_svg($type, $c1, $c2) {
    $a = valid_color($c1, '#ff7043');
    $b = valid_color($c2, '#ffca28');
    $s = "stroke='rgba(0,0,0,.25)' stroke-width='2'";
    $eye = fn($x, $y, $r) => "<circle cx='$x' cy='$y' r='$r' fill='#fff'/><circle cx='" . ($x - 1) . "' cy='$y' r='" . ($r * .55) . "' fill='#111'/>";
    $bg = "<rect width='200' height='150' fill='#d6f1ff'/><rect y='130' width='200' height='20' fill='#ead9b5'/>"
        . "<circle cx='28' cy='28' r='5' fill='#fff' opacity='.7'/><circle cx='38' cy='48' r='3' fill='#fff' opacity='.7'/>"
        . "<circle cx='172' cy='22' r='4' fill='#fff' opacity='.7'/>";
    switch ($type) {
        case 'goldfish':
            $g = "<path d='M118 76 C145 38 188 32 190 55 C176 70 176 82 190 97 C188 120 145 114 118 76Z' fill='$b' opacity='.95' $s/>"
               . "<path d='M68 47 C82 18 112 22 116 47Z' fill='$b'/>"
               . "<ellipse cx='85' cy='78' rx='45' ry='35' fill='$a' $s/>"
               . "<path d='M78 108 C82 128 100 128 100 110Z' fill='$b'/>"
               . "<path d='M72 60 q15 -10 30 0' stroke='#fff' stroke-width='3' fill='none' opacity='.5'/>"
               . $eye(56, 70, 7);
            break;
        case 'balloon':
            $g = "<path d='M126 76 L168 50 L168 102Z' fill='$b' $s/>"
               . "<path d='M78 44 C95 24 122 30 122 50Z' fill='$b'/>"
               . "<circle cx='92' cy='78' r='40' fill='$a' $s/>"
               . "<circle cx='102' cy='64' r='6' fill='$b' opacity='.7'/><circle cx='115' cy='88' r='5' fill='$b' opacity='.7'/>"
               . "<circle cx='86' cy='98' r='7' fill='$b' opacity='.7'/>"
               . "<path d='M53 84 q5 4 10 0' stroke='#333' stroke-width='2' fill='none'/>"
               . $eye(64, 70, 6);
            break;
        case 'plant':
            $g = "<path d='M100 132 Q97 80 100 30' stroke='$b' stroke-width='4' fill='none'/>"
               . "<ellipse cx='80' cy='95' rx='9' ry='30' fill='$a' transform='rotate(-30 80 95)'/>"
               . "<ellipse cx='120' cy='95' rx='9' ry='30' fill='$a' transform='rotate(30 120 95)'/>"
               . "<ellipse cx='84' cy='60' rx='8' ry='24' fill='$b' transform='rotate(-35 84 60)'/>"
               . "<ellipse cx='116' cy='60' rx='8' ry='24' fill='$b' transform='rotate(35 116 60)'/>"
               . "<ellipse cx='100' cy='32' rx='7' ry='18' fill='$a'/>"
               . "<ellipse cx='55' cy='112' rx='7' ry='22' fill='$b' transform='rotate(-15 55 112)'/>"
               . "<ellipse cx='145' cy='112' rx='7' ry='22' fill='$b' transform='rotate(15 145 112)'/>";
            break;
        case 'food':
            $g = "<rect x='68' y='24' width='64' height='104' rx='10' fill='$a' $s/>"
               . "<rect x='64' y='18' width='72' height='16' rx='5' fill='rgba(0,0,0,.35)'/>"
               . "<rect x='76' y='56' width='48' height='40' rx='6' fill='$b'/>"
               . "<text x='100' y='81' font-size='13' font-weight='bold' text-anchor='middle' fill='$a' font-family='Arial'>FOOD</text>"
               . "<circle cx='150' cy='124' r='4' fill='$a'/><circle cx='160' cy='127' r='4' fill='$a'/><circle cx='143' cy='128' r='3' fill='$a'/>";
            break;
        case 'decor':
            $g = "<rect x='60' y='50' width='22' height='80' fill='$a' $s/><rect x='118' y='50' width='22' height='80' fill='$a' $s/>"
               . "<rect x='78' y='72' width='44' height='58' fill='$a' $s/>"
               . "<rect x='60' y='42' width='6' height='9' fill='$a'/><rect x='68' y='42' width='6' height='9' fill='$a'/><rect x='76' y='42' width='6' height='9' fill='$a'/>"
               . "<rect x='118' y='42' width='6' height='9' fill='$a'/><rect x='126' y='42' width='6' height='9' fill='$a'/><rect x='134' y='42' width='6' height='9' fill='$a'/>"
               . "<path d='M90 130 V110 a10 10 0 0 1 20 0 V130Z' fill='$b'/>"
               . "<rect x='66' y='66' width='10' height='14' rx='5' fill='$b'/><rect x='124' y='66' width='10' height='14' rx='5' fill='$b'/>"
               . "<ellipse cx='32' cy='129' rx='22' ry='12' fill='$b'/><ellipse cx='172' cy='130' rx='20' ry='10' fill='$b'/>";
            break;
        case 'clean':
            $g = "<line x1='108' y1='78' x2='176' y2='20' stroke='$a' stroke-width='9' stroke-linecap='round'/>"
               . "<ellipse cx='76' cy='96' rx='40' ry='28' fill='$b' fill-opacity='.7' stroke='$a' stroke-width='6'/>"
               . "<path d='M46 86h60M40 97h72M46 108h60M61 72v50M76 69v55M91 72v50' stroke='$a' stroke-opacity='.4' stroke-width='2'/>";
            break;
        default: // guppy
            $g = "<path d='M108 75 C130 38 176 24 186 40 C196 60 196 90 186 110 C176 126 130 112 108 75Z' fill='$b' $s/>"
               . "<path d='M138 60 l10 6 M142 80 l12 0 M138 92 l10 -6' stroke='rgba(255,255,255,.5)' stroke-width='3'/>"
               . "<path d='M68 64 C80 44 100 50 104 65Z' fill='$b'/>"
               . "<ellipse cx='74' cy='76' rx='38' ry='15' fill='$a' $s/>"
               . $eye(48, 73, 4);
    }
    return "<svg viewBox='0 0 200 150' xmlns='http://www.w3.org/2000/svg' preserveAspectRatio='xMidYMid slice'>$bg$g</svg>";
}

/* ============================ เลย์เอาต์ ============================ */
function layout_top($title, $admin = false) {
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' | Aquarium Shop Ultimate</title><style>' . css() . '</style></head>';
    if ($admin) { ?>
<body class="admin">
<aside class="sidebar">
  <div class="logo">🐠 หลังร้าน Ultimate</div>
  <small>ผู้ใช้: <?= e($_SESSION['admin_name'] ?? '') ?></small>
  <a href="<?= url('admin') ?>">📊 แดชบอร์ด</a>
  <a href="<?= url('admin_products') ?>">📦 จัดการสินค้า</a>
  <a href="<?= url('admin_categories') ?>">🗂️ หมวดหมู่</a>
  <a href="<?= url('admin_orders') ?>">🧾 จัดการคำสั่งซื้อ</a>
  <a href="<?= url('admin_customers') ?>">👥 สมาชิกลูกค้า</a>
  <a href="<?= url('admin_reports') ?>">📈 รายงานยอดขาย</a>
  <a href="<?= url('admin_settings') ?>">⚙️ ตั้งค่า / แอดมิน</a>
  <a href="<?= url() ?>" target="_blank">🏪 ดูหน้าร้าน</a>
  <a href="<?= url('logout') ?>">🚪 ออกจากระบบ</a>
</aside>
<main class="admin-main">
<?php } else { ?>
<body>
<header class="topbar"><div class="container nav">
  <a class="logo" href="<?= url() ?>">🐠 Aquarium Shop</a>
  <form class="search">
    <input type="hidden" name="page" value="home">
    <input name="q" placeholder="ค้นหาสินค้า..." value="<?= e($_GET['q'] ?? '') ?>">
    <button>ค้นหา</button>
  </form>
  <nav>
    <a href="<?= url() ?>">สินค้าทั้งหมด</a>
    <a href="<?= url('track') ?>">ติดตามคำสั่งซื้อ</a>
    <a href="<?= url('cart') ?>">🛒 ตะกร้า (<?= cart_count() ?>)</a>
    <?php if (!empty($_SESSION['customer_id'])): ?>
      <a href="<?= url('customer_profile') ?>">👤 บัญชีของฉัน (<?= e($_SESSION['customer_name']) ?>)</a>
      <a href="<?= url('customer_logout') ?>">ออกจากระบบ</a>
    <?php else: ?>
      <a href="<?= url('customer_login') ?>">🔑 เข้าสู่ระบบ / สมัครสมาชิก</a>
    <?php endif; ?>
  </nav>
</div></header>
<main class="container">
<?php }
    echo show_flash();
}

function layout_bottom($admin = false) {
    echo '</main>';
    if (!$admin) echo '<footer class="footer">© ' . date('Y') . ' Aquarium Shop Ultimate Edition — ร้านปลาสวยงามออนไลน์ | <a href="' . url('admin') . '">เข้าสู่หลังร้าน</a></footer>';
    echo '</body></html>';
}

/* ============================ หน้าร้าน & สมาชิก ============================ */
function page_home(PDO $pdo) {
    $cat = (int)($_GET['cat'] ?? 0);
    $q = trim($_GET['q'] ?? '');
    $cats = $pdo->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();
    $sql = 'SELECT p.*, c.name AS cat_name, c.art FROM products p JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1';
    $params = [];
    if ($cat) { $sql .= ' AND p.category_id = ?'; $params[] = $cat; }
    if ($q !== '') { $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $sql .= ' ORDER BY c.sort_order, p.id';
    $st = $pdo->prepare($sql); $st->execute($params);
    $products = $st->fetchAll();

    layout_top('หน้าร้าน'); ?>
<section class="hero">
  <div class="hero-art"><?= art_svg('guppy', '#e53935', '#ff8a65') ?><?= art_svg('goldfish', '#ff9800', '#ffe0b2') ?><?= art_svg('balloon', '#fafafa', '#212121') ?></div>
  <h1>ร้านปลาสวยงามออนไลน์ Ultimate</h1>
  <p>ปลาหางนกยูง • ปลาทอง • ปลาบอลลูน • ไม้น้ำ • อาหารปลา • ของตกแต่ง • อุปกรณ์ทำความสะอาด</p>
  <p>🚚 ส่งฟรีเมื่อซื้อครบ <?= money(FREE_SHIP_MIN) ?></p>
</section>
<div class="cats">
  <a href="<?= url() ?>" class="<?= !$cat ? 'active' : '' ?>">ทั้งหมด</a>
  <?php foreach ($cats as $c): ?>
    <a href="<?= e(url('home', ['cat' => $c['id']])) ?>" class="<?= $cat == $c['id'] ? 'active' : '' ?>"><?= art_icon($c['art']) . ' ' . e($c['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php if ($q !== ''): ?><p>ผลการค้นหา "<?= e($q) ?>" พบ <?= count($products) ?> รายการ</p><?php endif; ?>
<div class="grid">
<?php foreach ($products as $p): ?>
  <div class="card">
    <a href="<?= e(url('product', ['id' => $p['id']])) ?>" class="thumb"><?= product_img($p) ?></a>
    <div class="card-body">
      <span class="tag"><?= e($p['cat_name']) ?></span>
      <h3><a href="<?= e(url('product', ['id' => $p['id']])) ?>"><?= e($p['name']) ?></a></h3>
      <?php if ($p['size']): ?><div class="size">ขนาด: <?= e($p['size']) ?></div><?php endif; ?>
      <div class="price"><?= money($p['price']) ?></div>
      <?php if ($p['stock'] > 0): ?>
        <form method="post" action="<?= url('cart') ?>">
          <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn">หยิบใส่ตะกร้า</button>
        </form>
      <?php else: ?>
        <button class="btn disabled" disabled>สินค้าหมด</button>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$products): ?><div class="box">ไม่พบสินค้า</div><?php endif;
    layout_bottom();
}

function page_product(PDO $pdo) {
    $st = $pdo->prepare('SELECT p.*, c.name AS cat_name, c.art FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.is_active = 1');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $p = $st->fetch();
    if (!$p) { flash('ไม่พบสินค้า', 'error'); go(); }
    layout_top($p['name']); ?>
<div class="detail">
  <div class="detail-img"><?= product_img($p) ?></div>
  <div>
    <a href="<?= e(url('home', ['cat' => $p['category_id']])) ?>" class="tag"><?= art_icon($p['art']) . ' ' . e($p['cat_name']) ?></a>
    <h1><?= e($p['name']) ?></h1>
    <?php if ($p['size']): ?><p>ขนาด: <b><?= e($p['size']) ?></b></p><?php endif; ?>
    <p class="price big"><?= money($p['price']) ?></p>
    <p><?= nl2br(e($p['description'])) ?></p>
    <p>คงเหลือ: <b><?= (int)$p['stock'] ?></b></p>
    <?php if ($p['stock'] > 0): ?>
      <form method="post" action="<?= url('cart') ?>" class="inline">
        <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>">
        <button class="btn">หยิบใส่ตะกร้า</button>
      </form>
    <?php else: ?><p class="danger">สินค้าหมดชั่วคราว</p><?php endif; ?>
  </div>
</div>
<?php layout_bottom();
}

function page_cart(PDO $pdo) {
    $_SESSION['cart'] ??= [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'add' || $action === 'update') {
            $st = $pdo->prepare('SELECT name, stock FROM products WHERE id = ? AND is_active = 1');
            $st->execute([$id]);
            if ($p = $st->fetch()) {
                $qty = max(1, (int)($_POST['qty'] ?? 1));
                $new = $action === 'add' ? ($_SESSION['cart'][$id] ?? 0) + $qty : $qty;
                if ($new > $p['stock']) { $new = (int)$p['stock']; flash('จำนวนเกินสต็อก ปรับเป็น ' . $new . ' ชิ้น', 'error'); }
                elseif ($action === 'add') flash('เพิ่ม "' . $p['name'] . '" ลงตะกร้าแล้ว');
                if ($new > 0) $_SESSION['cart'][$id] = $new; else unset($_SESSION['cart'][$id]);
            }
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$id]);
        } elseif ($action === 'clear') {
            $_SESSION['cart'] = [];
        }
        if ($action === 'add' && !empty($_SERVER['HTTP_REFERER'])) { header('Location: ' . $_SERVER['HTTP_REFERER']); exit; }
        go('cart');
    }
    [$items, $total] = cart_items($pdo);
    $ship = shipping_fee($total);
    layout_top('ตะกร้าสินค้า'); ?>
<h1>🛒 ตะกร้าสินค้า</h1>
<?php if (!$items): ?>
  <div class="box">ยังไม่มีสินค้าในตะกร้า <a href="<?= url() ?>">เลือกซื้อสินค้า</a></div>
<?php else: ?>
<table>
  <tr><th></th><th>สินค้า</th><th class="right">ราคา</th><th>จำนวน</th><th class="right">รวม</th><th></th></tr>
  <?php foreach ($items as $it): ?>
  <tr>
    <td><div class="mini-thumb"><?= product_img($it) ?></div></td>
    <td><?= e($it['name']) ?><?= $it['size'] ? ' <small>(' . e($it['size']) . ')</small>' : '' ?></td>
    <td class="right"><?= money($it['price']) ?></td>
    <td>
      <form method="post" class="inline">
        <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $it['id'] ?>">
        <input type="number" name="qty" value="<?= $it['qty'] ?>" min="1" max="<?= (int)$it['stock'] ?>">
        <button class="btn sm gray">อัปเดต</button>
      </form>
    </td>
    <td class="right"><?= money($it['subtotal']) ?></td>
    <td>
      <form method="post"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $it['id'] ?>">
        <button class="btn sm red">ลบ</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="4" class="right">ยอดสินค้า</td><td class="right"><?= money($total) ?></td><td></td></tr>
  <tr><td colspan="4" class="right">ค่าจัดส่ง <?= $ship == 0 ? '(ส่งฟรี)' : '' ?></td><td class="right"><?= money($ship) ?></td><td></td></tr>
  <tr><th colspan="4" class="right">ยอดรวมทั้งสิ้น</th><th class="right"><?= money($total + $ship) ?></th><th></th></tr>
</table>
<div class="toolbar">
  <form method="post"><input type="hidden" name="action" value="clear"><button class="btn gray">ล้างตะกร้า</button></form>
  <a class="btn" href="<?= url() ?>">เลือกซื้อต่อ</a>
  <a class="btn orange" href="<?= url('checkout') ?>">ดำเนินการสั่งซื้อ →</a>
</div>
<?php endif;
    layout_bottom();
}

function page_checkout(PDO $pdo) {
    [$items, $total] = cart_items($pdo);
    if (!$items) go('cart');

    $cust = null;
    if (!empty($_SESSION['customer_id'])) {
        $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $st->execute([$_SESSION['customer_id']]);
        $cust = $st->fetch();
    }

    $errors = [];
    $f = [
        'name'    => $cust['name'] ?? '',
        'phone'   => $cust['phone'] ?? '',
        'address' => $cust['address'] ?? '',
        'note'    => '',
        'payment' => 'transfer'
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($f as $k => $v) $f[$k] = trim($_POST[$k] ?? '');
        if ($f['name'] === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
        if (!preg_match('/^0\d{8,9}$/', $f['phone'])) $errors[] = 'เบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)';
        if ($f['address'] === '') $errors[] = 'กรุณากรอกที่อยู่จัดส่ง';
        if (!in_array($f['payment'], ['transfer', 'cod'], true)) $f['payment'] = 'transfer';

        $slipImg = null;
        if ($f['payment'] === 'transfer') {
            if (!empty($_FILES['slip']['name'])) {
                $sf = $_FILES['slip'];
                $ext = strtolower(pathinfo($sf['name'], PATHINFO_EXTENSION));
                if ($sf['error'] !== UPLOAD_ERR_OK) $errors[] = 'อัปโหลดสลิปไม่สำเร็จ';
                elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) $errors[] = 'รองรับสลิปไฟล์ jpg, png, webp เท่านั้น';
                elseif ($sf['size'] > 3 * 1024 * 1024) $errors[] = 'ไฟล์สลิปต้องไม่เกิน 3 MB';
                else {
                    $slipImg = uniqid('slip_') . '.' . $ext;
                    move_uploaded_file($sf['tmp_name'], UPLOAD_DIR . $slipImg);
                }
            } else {
                $errors[] = 'กรุณาแนบสลิปการโอนเงิน';
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT id, name, size, price, stock FROM products WHERE id = ? FOR UPDATE');
                $lines = []; $sum = 0;
                foreach ($_SESSION['cart'] as $pid => $qty) {
                    $lock->execute([$pid]);
                    $p = $lock->fetch();
                    if (!$p || $p['stock'] < $qty) throw new Exception('สินค้า "' . ($p['name'] ?? '#' . $pid) . '" มีจำนวนไม่พอ');
                    $lines[] = [$p, $qty];
                    $sum += $p['price'] * $qty;
                }
                $ship = shipping_fee($sum);
                $custId = !empty($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;

                $pdo->prepare('INSERT INTO orders (customer_id, customer_name, phone, address, note, payment_method, slip_image, subtotal, shipping, total) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$custId, $f['name'], $f['phone'], $f['address'], $f['note'], $f['payment'], $slipImg, $sum, $ship, $sum + $ship]);
                $oid = (int)$pdo->lastInsertId();

                $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, qty) VALUES (?,?,?,?,?)');
                $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                foreach ($lines as [$p, $qty]) {
                    $ins->execute([$oid, $p['id'], $p['name'] . ($p['size'] ? ' (' . $p['size'] . ')' : ''), $p['price'], $qty]);
                    $upd->execute([$qty, $p['id']]);
                }
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['last_order'] = $oid;
                go('success', ['id' => $oid]);
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($slipImg && is_file(UPLOAD_DIR . $slipImg)) @unlink(UPLOAD_DIR . $slipImg);
                $errors[] = $ex->getMessage();
            }
        }
    }
    $ship = shipping_fee($total);
    layout_top('สั่งซื้อสินค้า'); ?>
<h1>📝 สั่งซื้อสินค้า</h1>
<?php if (empty($_SESSION['customer_id'])): ?>
  <div class="box" style="background:#e1f5fe">มีบัญชีอยู่แล้ว? <a href="<?= url('customer_login') ?>"><b>เข้าสู่ระบบ</b></a> เพื่อความรวดเร็วในการกรอกข้อมูล</div>
<?php endif; ?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="detail" style="align-items:start">
  <div class="form flat" style="padding:0">
    <label>ชื่อ-นามสกุล *</label><input name="name" value="<?= e($f['name']) ?>" required>
    <label>เบอร์โทรศัพท์ *</label><input name="phone" value="<?= e($f['phone']) ?>" required>
    <label>ที่อยู่จัดส่ง *</label><textarea name="address" rows="4" required><?= e($f['address']) ?></textarea>
    <label>หมายเหตุ</label><textarea name="note" rows="2"><?= e($f['note']) ?></textarea>
    <label>วิธีชำระเงิน *</label>
    <select name="payment" id="payMethod" onchange="toggleSlip(this.value)">
      <option value="transfer" <?= $f['payment'] === 'transfer' ? 'selected' : '' ?>>โอนเงินผ่านธนาคาร</option>
      <option value="cod" <?= $f['payment'] === 'cod' ? 'selected' : '' ?>>เก็บเงินปลายทาง</option>
    </select>
    <div id="bankBox" style="background:#e1f5fe;padding:12px;border-radius:8px;margin-top:10px">
      <p style="margin:0 0 6px">โอนเงินมาที่: <b><?= e(BANK_INFO) ?></b></p>
      <label>แนบสลิปการโอนเงิน *</label>
      <input type="file" name="slip" accept="image/*">
    </div>
    <p><small style="color:#666">* ปลามีชีวิตจัดส่งแบบด่วน แพ็คถุงออกซิเจนอย่างดี</small></p>
    <button class="btn orange" style="width:100%;padding:12px">ยืนยันการสั่งซื้อ</button>
  </div>
  <div>
    <h3>สรุปรายการ</h3>
    <table>
      <?php foreach ($items as $it): ?>
      <tr><td><div class="mini-thumb"><?= product_img($it) ?></div></td>
          <td><?= e($it['name']) ?><?= $it['size'] ? ' (' . e($it['size']) . ')' : '' ?> × <?= $it['qty'] ?></td>
          <td class="right"><?= money($it['subtotal']) ?></td></tr>
      <?php endforeach; ?>
      <tr><td></td><td>ค่าจัดส่ง</td><td class="right"><?= money($ship) ?></td></tr>
      <tr><th></th><th>ยอดรวม</th><th class="right"><?= money($total + $ship) ?></th></tr>
    </table>
  </div>
</form>
<script>
function toggleSlip(v) {
    document.getElementById('bankBox').style.display = (v === 'transfer') ? 'block' : 'none';
}
toggleSlip(document.getElementById('payMethod').value);
</script>
<?php layout_bottom();
}

function page_success(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    if (($_SESSION['last_order'] ?? 0) !== $id) go();
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) go();
    layout_top('สั่งซื้อสำเร็จ'); ?>
<div class="box center">
  <h1>✅ สั่งซื้อสำเร็จ!</h1>
  <p>เลขที่คำสั่งซื้อ: <b>#<?= $o['id'] ?></b></p>
  <p>ยอดชำระ: <b class="price"><?= money($o['total']) ?></b></p>
  <p>สถานะ: <?= status_badge($o['status']) ?></p>
  <p>
    <a href="<?= e(url('track', ['id' => $o['id'], 'phone' => $o['phone']])) ?>" class="btn">ติดตามสถานะ</a>
    <a href="<?= e(url('invoice', ['id' => $o['id']])) ?>" target="_blank" class="btn gray">🖨️ พิมพ์ใบเสร็จ</a>
  </p>
  <p style="margin-top:20px"><a href="<?= url() ?>">กลับสู่หน้าแรก</a></p>
</div>
<?php layout_bottom();
}

function page_track(PDO $pdo) {
    $order = null; $items = []; $searched = false;
    $id = (int)($_GET['id'] ?? 0); $phone = trim($_GET['phone'] ?? '');
    if ($id && $phone !== '') {
        $searched = true;
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND phone = ?'); $st->execute([$id, $phone]);
        if ($order = $st->fetch()) {
            $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?'); $st->execute([$id]);
            $items = $st->fetchAll();
        }
    }
    layout_top('ติดตามคำสั่งซื้อ'); ?>
<h1>🔎 ติดตามคำสั่งซื้อ</h1>
<form class="toolbar box">
  <input type="hidden" name="page" value="track">
  <input name="id" type="number" placeholder="เลขที่คำสั่งซื้อ" value="<?= $id ?: '' ?>" required style="width:180px">
  <input name="phone" placeholder="เบอร์โทรที่ใช้สั่งซื้อ" value="<?= e($phone) ?>" required>
  <button class="btn">ตรวจสอบ</button>
</form>
<?php if ($searched && !$order): ?><div class="alert error">ไม่พบคำสั่งซื้อ หรือเบอร์โทรไม่ถูกต้อง</div><?php endif; ?>
<?php if ($order): ?>
<div class="box">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3>คำสั่งซื้อ #<?= $order['id'] ?> <?= status_badge($order['status']) ?></h3>
    <a href="<?= e(url('invoice', ['id' => $order['id']])) ?>" target="_blank" class="btn sm gray">🖨️ พิมพ์ใบเสร็จ</a>
  </div>
  <p>วันที่สั่ง: <?= e($order['created_at']) ?> | ผู้รับ: <?= e($order['customer_name']) ?> (<?= e($order['phone']) ?>)</p>
  <p>ที่อยู่จัดส่ง: <?= nl2br(e($order['address'])) ?></p>
  <p>
  บริษัทขนส่ง:
  <?= ($o['shipping_company'] ?? 'flash') === 'jnt'
    ? 'J&T Express'
    : 'Flash Express' ?>
  <table>
    <?php foreach ($items as $it): ?>
    <tr><td><?= e($it['product_name']) ?> × <?= $it['qty'] ?></td><td class="right"><?= money($it['price'] * $it['qty']) ?></td></tr>
    <?php endforeach; ?>
    <tr><td>ค่าจัดส่ง</td><td class="right"><?= money($order['shipping']) ?></td></tr>
    <tr><th>ยอดรวมทั้งสิ้น</th><th class="right"><?= money($order['total']) ?></th></tr>
  </table>
  <?php if (!empty($order['slip_image']) && is_file(UPLOAD_DIR . $order['slip_image'])): ?>
    <p><b>สลิปโอนเงินที่แนบ:</b><br><a href="uploads/<?= e($order['slip_image']) ?>" target="_blank"><img src="uploads/<?= e($order['slip_image']) ?>" style="max-width:200px;border-radius:6px;margin-top:6px"></a></p>
  <?php endif; ?>
</div>
<?php endif;
    layout_bottom();
}

function page_invoice(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) die('ไม่พบคำสั่งซื้อ');
    $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?'); $st->execute([$id]);
    $items = $st->fetchAll();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ใบเสร็จรับเงิน #' . $o['id'] . '</title><style>
    body{font-family:Tahoma,sans-serif;color:#111;padding:30px;max-width:700px;margin:auto;font-size:14px}
    h2{margin:0 0 5px;color:#01579b}.right{text-align:right}.left{text-align:left}
    table{width:100%;border-collapse:collapse;margin:20px 0}
    th,td{padding:8px;border-bottom:1px solid #ddd;font-size:13px}th{background:#f0f7fb}
    .box{border:1px solid #ccc;padding:15px;border-radius:8px;margin-bottom:15px}
    @media print{body{padding:0} .no-print{display:none}}</style></head><body>';
    echo '<div style="display:flex;justify-content:space-between"><div><h2>🐠 Aquarium Shop Ultimate</h2><p>ร้านปลาสวยงามและอุปกรณ์ครบวงจร</p></div>'
       . '<div class="right"><h3>ใบเสร็จรับเงิน / ใบกำกับสินค้า</h3><p>เลขที่: <b>#' . $o['id'] . '</b><br>วันที่: ' . $o['created_at'] . '</p></div></div><hr>';
    echo '<div class="box"><b>ลูกค้า:</b> ' . e($o['customer_name']) . ' (โทร: ' . e($o['phone']) . ')<br><b>ที่อยู่จัดส่ง:</b> ' . nl2br(e($o['address'])) . '</div>';
    echo '<table><tr><th>รายการ</th><th class="right">ราคา/หน่วย</th><th class="right">จำนวน</th><th class="right">รวม</th></tr>';
    foreach ($items as $it) {
        echo '<tr><td>' . e($it['product_name']) . '</td><td class="right">' . money($it['price']) . '</td><td class="right">' . $it['qty'] . '</td><td class="right">' . money($it['price'] * $it['qty']) . '</td></tr>';
    }
    echo '<tr><td colspan="3" class="right">ยอดรวมสินค้า</td><td class="right">' . money($o['subtotal']) . '</td></tr>';
    echo '<tr><td colspan="3" class="right">ค่าจัดส่ง</td><td class="right">' . money($o['shipping']) . '</td></tr>';
    echo '<tr><th colspan="3" class="right">ยอดสุทธิทั้งสิ้น</th><th class="right">' . money($o['total']) . '</th></tr></table>';
    echo '<p style="text-align:center;color:#666;margin-top:40px">ขอบคุณที่ใช้บริการ Aquarium Shop — ขอให้มีความสุขกับการเลี้ยงปลาครับ/ค่ะ</p>';
    echo '<div class="no-print center" style="text-align:center"><button onclick="window.print()" style="padding:10px 20px;background:#0277bd;color:#fff;border:0;border-radius:6px;cursor:pointer">🖨️ พิมพ์เอกสาร</button></div>';
    echo '</body></html>';
    exit;
}

/* ============================ ระบบสมาชิก (Customer) ============================ */
function page_customer_login(PDO $pdo) {
    if (!empty($_SESSION['customer_id'])) go('customer_profile');
    $error = ''; $tab = $_GET['tab'] ?? 'login';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'login') {
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $st = $pdo->prepare('SELECT * FROM customers WHERE email = ?'); $st->execute([$email]);
            $c = $st->fetch();
            if ($c && password_verify($pass, $c['password'])) {
                session_regenerate_id(true);
                $_SESSION['customer_id'] = $c['id'];
                $_SESSION['customer_name'] = $c['name'];
                flash('เข้าสู่ระบบสำเร็จ ยินดีต้อนรับคุณ ' . $c['name']);
                go('home');
            }
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
            $tab = 'login';
        } elseif ($action === 'register') {
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $pass    = $_POST['password'] ?? '';
            $phone   = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $tab = 'register';

            if ($name === '' || $email === '' || $pass === '' || $phone === '' || $address === '') {
                $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'รูปแบบอีเมลไม่ถูกต้อง';
            } elseif (strlen($pass) < 6) {
                $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
            } else {
                $st = $pdo->prepare('SELECT id FROM customers WHERE email = ?'); $st->execute([$email]);
                if ($st->fetch()) {
                    $error = 'อีเมลนี้ถูกใช้งานแล้ว';
                } else {
                    $pdo->prepare('INSERT INTO customers (name, email, password, phone, address) VALUES (?,?,?,?,?)')
                        ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $phone, $address]);
                    $newId = $pdo->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION['customer_id'] = $newId;
                    $_SESSION['customer_name'] = $name;
                    flash('สมัครสมาชิกสำเร็จ!');
                    go('home');
                }
            }
        }
    }
    layout_top('เข้าสู่ระบบ / สมัครสมาชิก'); ?>
<div style="max-width:480px;margin:30px auto">
  <div class="cats" style="justify-content:center;margin-bottom:15px">
    <a href="<?= url('customer_login', ['tab' => 'login']) ?>" class="<?= $tab === 'login' ? 'active' : '' ?>">เข้าสู่ระบบ</a>
    <a href="<?= url('customer_login', ['tab' => 'register']) ?>" class="<?= $tab === 'register' ? 'active' : '' ?>">สมัครสมาชิกใหม่</a>
  </div>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

  <?php if ($tab === 'login'): ?>
  <div class="box">
    <h2>🔑 เข้าสู่ระบบลูกค้า</h2>
    <form method="post" class="form flat">
      <input type="hidden" name="action" value="login">
      <label>อีเมล</label><input type="email" name="email" required autofocus>
      <label>รหัสผ่าน</label><input type="password" name="password" required>
      <br><button class="btn" style="width:100%;margin-top:10px">เข้าสู่ระบบ</button>
    </form>
  </div>
  <?php else: ?>
  <div class="box">
    <h2>📝 สมัครสมาชิก</h2>
    <form method="post" class="form flat">
      <input type="hidden" name="action" value="register">
      <label>ชื่อ-นามสกุล *</label><input name="name" required>
      <label>อีเมล *</label><input type="email" name="email" required>
      <label>รหัสผ่าน (อย่างน้อย 6 ตัว) *</label><input type="password" name="password" required>
      <label>เบอร์โทรศัพท์ *</label><input name="phone" required>
      <label>ที่อยู่จัดส่งเริ่มต้น *</label><textarea name="address" rows="3" required></textarea>
      <br><button class="btn orange" style="width:100%;margin-top:10px">สมัครสมาชิก</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php layout_bottom();
}

function page_customer_logout() {
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    flash('ออกจากระบบแล้ว');
    go('home');
}

function page_customer_profile(PDO $pdo) {
    if (empty($_SESSION['customer_id'])) go('customer_login');
    $cid = $_SESSION['customer_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($name !== '' && $phone !== '' && $address !== '') {
            $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?')->execute([$name, $phone, $address, $cid]);
            $_SESSION['customer_name'] = $name;
            flash('อัปเดตข้อมูลส่วนตัวเรียบร้อย');
        } else {
            flash('กรุณากรอกข้อมูลให้ครบถ้วน', 'error');
        }
        go('customer_profile');
    }

    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?'); $st->execute([$cid]);
    $c = $st->fetch();
    $stO = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC'); $stO->execute([$cid]);
    $orders = $stO->fetchAll();

    layout_top('บัญชีของฉัน'); ?>
<h1>👤 บัญชีผู้ใช้: <?= e($c['name']) ?></h1>
<div class="stats" style="grid-template-columns:1fr 2fr">
  <div class="box" style="margin:0">
    <h3>ข้อมูลส่วนตัว</h3>
    <form method="post" class="form flat">
      <label>ชื่อ-นามสกุล</label><input name="name" value="<?= e($c['name']) ?>" required>
      <label>อีเมล (เปลี่ยนไม่ได้)</label><input value="<?= e($c['email']) ?>" disabled>
      <label>เบอร์โทรศัพท์</label><input name="phone" value="<?= e($c['phone']) ?>" required>
      <label>ที่อยู่จัดส่ง</label><textarea name="address" rows="3" required><?= e($c['address']) ?></textarea>
      <br><button class="btn" style="margin-top:8px">บันทึกข้อมูล</button>
    </form>
  </div>
  <div class="box" style="margin:0">
    <h3>📦 ประวัติการสั่งซื้อของคุณ</h3>
    <table>
      <tr><th>#</th><th>วันที่</th><th class="right">ยอดรวม</th><th>สถานะ</th><th>จัดการ</th></tr>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td>#<?= $o['id'] ?></td>
        <td><?= e($o['created_at']) ?></td>
        <td class="right"><?= money($o['total']) ?></td>
        <td><?= status_badge($o['status']) ?></td>
        <td>
          <a href="<?= e(url('track', ['id' => $o['id'], 'phone' => $c['phone']])) ?>" class="btn sm">ดู</a>
          <a href="<?= e(url('invoice', ['id' => $o['id']])) ?>" target="_blank" class="btn sm gray">พิมพ์</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="5">คุณยังไม่มีประวัติการสั่งซื้อ</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php layout_bottom();
}

/* ============================ ระบบหลังร้าน (Admin Ultimate) ============================ */
function page_login(PDO $pdo) {
    if (!empty($_SESSION['admin_id'])) go('admin');
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $st = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([trim($_POST['username'] ?? '')]);
        $a = $st->fetch();
        if ($a && password_verify($_POST['password'] ?? '', $a['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $a['id'];
            $_SESSION['admin_name'] = $a['username'];
            go('admin');
        }
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
    layout_top('เข้าสู่ระบบหลังร้าน'); ?>
<div class="login-box">
  <h2>🐠 เข้าสู่ระบบหลังร้าน Ultimate</h2>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form flat">
    <label>ชื่อผู้ใช้</label><input name="username" required autofocus>
    <label>รหัสผ่าน</label><input type="password" name="password" required>
    <br><br><button class="btn" style="width:100%">เข้าสู่ระบบ</button>
  </form>
  <p><small>ค่าเริ่มต้น: admin / admin123</small></p>
</div>
<?php layout_bottom();
}

function page_logout(PDO $pdo) {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    go('login');
}

function admin_dashboard(PDO $pdo) {
    $stats = [
        'สินค้าทั้งหมด'    => $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'สมาชิกลูกค้า'    => $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'คำสั่งซื้อทั้งหมด' => $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'ยอดขายรวม (สำเร็จ)' => money($pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='completed'")->fetchColumn()),
    ];
    $low = $pdo->query('SELECT p.*, c.art FROM products p JOIN categories c ON c.id = p.category_id WHERE p.stock <= 5 ORDER BY p.stock LIMIT 10')->fetchAll();
    $recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
    layout_top('แดชบอร์ด', true); ?>
    <td><?= e($o['tracking_number'] ?? 'ยังไม่มี') ?></td>

<h2>📊 แดชบอร์ดผู้ดูแลระบบ</h2>
<div class="stats">
  <?php foreach ($stats as $label => $val): ?><div class="stat"><?= e($label) ?><b><?= e($val) ?></b></div><?php endforeach; ?>
</div>
<h3>🧾 คำสั่งซื้อล่าสุด</h3>
<table>
  <tr><th>#</th><th>ลูกค้า</th><th>วันที่</th><th class="right">ยอดรวม</th><th>สถานะ</th><th>จัดการ</th></tr>
  <?php foreach ($recent as $o): ?>
  <tr><td>#<?= $o['id'] ?></td><td><?= e($o['customer_name']) ?></td>
      <td><?= e($o['created_at']) ?></td><td class="right"><?= money($o['total']) ?></td><td><?= status_badge($o['status']) ?></td>
      <td><a class="btn sm" href="<?= e(url('admin_order', ['id' => $o['id']])) ?>">ตรวจสอบ</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="6">ยังไม่มีคำสั่งซื้อ</td></tr><?php endif; ?>
</table>
<h3>⚠️ สินค้าใกล้หมด (≤ 5 ชิ้น)</h3>
<table>
  <tr><th></th><th>สินค้า</th><th>ขนาด</th><th class="right">คงเหลือ</th><th>จัดการ</th></tr>
  <?php foreach ($low as $p): ?>
  <tr><td><div class="mini-thumb"><?= product_img($p) ?></div></td><td><?= e($p['name']) ?></td><td><?= e($p['size']) ?></td>
      <td class="right danger"><?= $p['stock'] ?></td><td><a href="<?= e(url('admin_product_form', ['id' => $p['id']])) ?>">เติมสต็อก</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$low): ?><tr><td colspan="5">สต็อกเพียงพอทุกรายการ</td></tr><?php endif; ?>
</table>
<?php layout_bottom(true);
}

function admin_products(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $st = $pdo->prepare('SELECT image FROM products WHERE id = ?'); $st->execute([$id]);
            $img = $st->fetchColumn();
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            if ($img && is_file(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);
            flash('ลบสินค้าเรียบร้อย');
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('เปลี่ยนสถานะการแสดงผลแล้ว');
        } elseif ($action === 'quick_stock') {
            $newStock = max(0, (int)($_POST['stock'] ?? 0));
            $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $id]);
            flash('อัปเดตสต็อกเรียบร้อย');
        }
        header('Location: ?' . http_build_query($_GET)); exit;
    }
    $cat = (int)($_GET['cat'] ?? 0); $q = trim($_GET['q'] ?? '');
    $cats = $pdo->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();
    $sql = 'SELECT p.*, c.name AS cat_name, c.art FROM products p JOIN categories c ON c.id = p.category_id WHERE 1';
    $params = [];
    if ($cat) { $sql .= ' AND p.category_id = ?'; $params[] = $cat; }
    if ($q !== '') { $sql .= ' AND p.name LIKE ?'; $params[] = "%$q%"; }
    $sql .= ' ORDER BY c.sort_order, p.id';
    $st = $pdo->prepare($sql); $st->execute($params); $products = $st->fetchAll();
    layout_top('จัดการสินค้า', true); ?>
<div class="toolbar">
  <h2 style="margin:0;flex:1">📦 จัดการสินค้า (<?= count($products) ?>)</h2>
  <a class="btn" href="<?= url('admin_product_form') ?>">+ เพิ่มสินค้า</a>
</div>
<form class="toolbar">
  <input type="hidden" name="page" value="admin_products">
  <select name="cat">
    <option value="0">ทุกหมวดหมู่</option>
    <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <input name="q" placeholder="ค้นหาชื่อสินค้า" value="<?= e($q) ?>">
  <button class="btn sm">กรอง</button>
</form>
<table>
  <tr><th>รูป</th><th>ชื่อสินค้า</th><th>หมวดหมู่</th><th>ขนาด</th><th class="right">ราคา</th><th>สต็อกด่วน</th><th>สถานะ</th><th>จัดการ</th></tr>
  <?php foreach ($products as $p): ?>
  <tr>
    <td><div class="mini-thumb"><?= product_img($p) ?></div></td>
    <td><?= e($p['name']) ?></td><td><?= e($p['cat_name']) ?></td><td><?= e($p['size']) ?></td>
    <td class="right"><?= money($p['price']) ?></td>
    <td>
      <form method="post" class="inline" style="gap:4px">
        <input type="hidden" name="action" value="quick_stock">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="number" name="stock" value="<?= $p['stock'] ?>" min="0" style="width:70px">
        <button class="btn sm gray">บันทึก</button>
      </form>
    </td>
    <td>
      <form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn sm <?= $p['is_active'] ? '' : 'gray' ?>"><?= $p['is_active'] ? 'แสดง' : 'ซ่อน' ?></button></form>
    </td>
    <td class="inline">
      <a class="btn sm orange" href="<?= e(url('admin_product_form', ['id' => $p['id']])) ?>">แก้ไข</a>
      <form method="post" onsubmit="return confirm('ยืนยันการลบสินค้านี้?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn sm red">ลบ</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php layout_bottom(true);
}

function admin_product_form(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    $p = ['category_id' => '', 'name' => '', 'size' => '', 'description' => '', 'price' => '', 'stock' => 0,
          'color1' => '#ff7043', 'color2' => '#ffca28', 'image' => null, 'is_active' => 1, 'art' => 'guppy'];
    if ($id) {
        $st = $pdo->prepare('SELECT p.*, c.art FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
        $st->execute([$id]);
        if (!($p = $st->fetch())) { flash('ไม่พบสินค้า', 'error'); go('admin_products'); }
    }
    $cats = $pdo->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $p['category_id'] = (int)($_POST['category_id'] ?? 0);
        $p['name']        = trim($_POST['name'] ?? '');
        $p['size']        = trim($_POST['size'] ?? '');
        $p['description'] = trim($_POST['description'] ?? '');
        $p['price']       = (float)($_POST['price'] ?? 0);
        $p['stock']       = max(0, (int)($_POST['stock'] ?? 0));
        $p['color1']      = valid_color($_POST['color1'] ?? '', '#ff7043');
        $p['color2']      = valid_color($_POST['color2'] ?? '', '#ffca28');
        $p['is_active']   = isset($_POST['is_active']) ? 1 : 0;
        if (!$p['category_id']) $errors[] = 'กรุณาเลือกหมวดหมู่';
        if ($p['name'] === '') $errors[] = 'กรุณากรอกชื่อสินค้า';
        if ($p['price'] <= 0) $errors[] = 'ราคาต้องมากกว่า 0';

        $newImg = null;
        if (!empty($_FILES['image']['name'])) {
            $f = $_FILES['image'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($f['error'] !== UPLOAD_ERR_OK) $errors[] = 'อัปโหลดรูปไม่สำเร็จ';
            elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $errors[] = 'รองรับเฉพาะไฟล์ jpg, png, gif, webp';
            elseif ($f['size'] > 2 * 1024 * 1024) $errors[] = 'ไฟล์รูปต้องไม่เกิน 2 MB';
            else {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
                $newImg = uniqid('p_') . '.' . $ext;
                move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $newImg);
            }
        }

        if (!$errors) {
            $removeOld = $newImg || isset($_POST['remove_image']);
            if ($removeOld && $p['image'] && is_file(UPLOAD_DIR . $p['image'])) @unlink(UPLOAD_DIR . $p['image']);
            if ($removeOld) $p['image'] = $newImg;
            $data = [$p['category_id'], $p['name'], $p['size'] ?: null, $p['description'], $p['price'], $p['stock'],
                     $p['color1'], $p['color2'], $p['image'], $p['is_active']];
            if ($id) {
                $data[] = $id;
                $pdo->prepare('UPDATE products SET category_id=?, name=?, size=?, description=?, price=?, stock=?, color1=?, color2=?, image=?, is_active=? WHERE id=?')->execute($data);
                flash('บันทึกการแก้ไขเรียบร้อย');
            } else {
                $pdo->prepare('INSERT INTO products (category_id, name, size, description, price, stock, color1, color2, image, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($data);
                flash('เพิ่มสินค้าเรียบร้อย');
            }
            go('admin_products');
        }
    }
    layout_top($id ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า', true); ?>
<h2><?= $id ? '✏️ แก้ไขสินค้า' : '➕ เพิ่มสินค้า' ?></h2>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form">
  <div class="detail-img" style="max-width:280px"><?= product_img($p) ?></div>
  <label>หมวดหมู่ *</label>
  <select name="category_id" required>
    <option value="">-- เลือกหมวดหมู่ --</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $p['category_id'] == $c['id'] ? 'selected' : '' ?>><?= art_icon($c['art']) . ' ' . e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label>ชื่อสินค้า / สายพันธุ์ *</label><input name="name" value="<?= e($p['name']) ?>" required>
  <label>ขนาด (เช่น 3 นิ้ว, 100 g, คู่)</label><input name="size" value="<?= e($p['size']) ?>">
  <label>รายละเอียด</label><textarea name="description" rows="4"><?= e($p['description']) ?></textarea>
  <label>ราคา (บาท) *</label><input type="number" step="0.01" name="price" value="<?= e($p['price']) ?>" required style="width:160px">
  <label>จำนวนสต็อก</label><input type="number" name="stock" value="<?= (int)$p['stock'] ?>" min="0">
  <label>สีของรูปวาดอัตโนมัติ (ใช้เมื่อไม่ได้อัปโหลดรูป)</label>
  <div class="inline">สีหลัก <input type="color" name="color1" value="<?= e($p['color1']) ?>"> สีรอง/หาง <input type="color" name="color2" value="<?= e($p['color2']) ?>"></div>
  <label>อัปโหลดรูปจริง (jpg/png/webp ≤ 2MB)</label>
  <input type="file" name="image" accept="image/*">
  <?php if ($p['image']): ?><label><input type="checkbox" name="remove_image" style="width:auto"> ลบรูปที่อัปโหลด (กลับไปใช้รูปวาด)</label><?php endif; ?>
  <label><input type="checkbox" name="is_active" style="width:auto" <?= $p['is_active'] ? 'checked' : '' ?>> แสดงสินค้าบนหน้าร้าน</label>
  <br><button class="btn">💾 บันทึก</button> <a href="<?= url('admin_products') ?>" class="btn gray">ยกเลิก</a>
</form>
<?php layout_bottom(true);
}

function admin_categories(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $art  = array_key_exists($_POST['art'] ?? '', ART_ICON) ? $_POST['art'] : 'guppy';
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($action === 'add' && $name !== '') {
            $pdo->prepare('INSERT INTO categories (name, art, sort_order) VALUES (?,?,?)')->execute([$name, $art, $sort]);
            flash('เพิ่มหมวดหมู่แล้ว');
        } elseif ($action === 'update' && $name !== '') {
            $pdo->prepare('UPDATE categories SET name=?, art=?, sort_order=? WHERE id=?')->execute([$name, $art, $sort, $id]);
            flash('บันทึกหมวดหมู่แล้ว');
        } elseif ($action === 'delete') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?'); $st->execute([$id]);
            if ($st->fetchColumn() > 0) flash('ลบไม่ได้ เพราะยังมีสินค้าในหมวดนี้', 'error');
            else { $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]); flash('ลบหมวดหมู่แล้ว'); }
        }
        go('admin_categories');
    }
    $cats = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS total FROM categories c ORDER BY sort_order, id')->fetchAll();
    $artSelect = function ($cur) {
        $h = '<select name="art" style="width:auto">';
        foreach (ART_LABEL as $k => $v) $h .= '<option value="' . $k . '"' . ($cur === $k ? ' selected' : '') . '>' . ART_ICON[$k] . ' ' . $v . '</option>';
        return $h . '</select>';
    };
    layout_top('หมวดหมู่', true); ?>
<h2>🗂️ จัดการหมวดหมู่สินค้า</h2>
<form method="post" class="toolbar box">
  <input type="hidden" name="action" value="add">
  <input name="name" placeholder="ชื่อหมวดหมู่ใหม่" required>
  รูปแบบรูป: <?= $artSelect('guppy') ?>
  <input type="number" name="sort_order" placeholder="ลำดับ" style="width:80px">
  <button class="btn">+ เพิ่มหมวดหมู่</button>
</form>
<table>
  <tr><th>ชื่อ / รูปแบบ / ลำดับ</th><th class="right">จำนวนสินค้า</th><th>จัดการ</th></tr>
  <?php foreach ($cats as $c): ?>
  <tr>
    <td>
      <form method="post" class="inline">
        <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <input name="name" value="<?= e($c['name']) ?>"> <?= $artSelect($c['art']) ?>
        <input type="number" name="sort_order" value="<?= $c['sort_order'] ?>" style="width:80px">
        <button class="btn sm">บันทึก</button>
      </form>
    </td>
    <td class="right"><?= $c['total'] ?></td>
    <td><form method="post" onsubmit="return confirm('ลบหมวดหมู่นี้?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <button class="btn sm red">ลบ</button></form></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php layout_bottom(true);
}

function admin_orders(PDO $pdo) {
    $status = $_GET['status'] ?? '';
    $q = trim($_GET['q'] ?? '');
    $sql = 'SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS items FROM orders o WHERE 1';
    $params = [];
    if (array_key_exists($status, ORDER_STATUS)) { $sql .= ' AND o.status = ?'; $params[] = $status; }
    if ($q !== '') { $sql .= ' AND (o.id = ? OR o.customer_name LIKE ? OR o.phone LIKE ?)'; $params[] = $q; $params[] = "%$q%"; $params[] = "%$q%"; }
    $sql .= ' ORDER BY o.id DESC';
    $st = $pdo->prepare($sql); $st->execute($params); $orders = $st->fetchAll();
    layout_top('คำสั่งซื้อ', true); ?>
<h2>🧾 จัดการคำสั่งซื้อ</h2>
<form class="toolbar box">
  <input type="hidden" name="page" value="admin_orders">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input name="q" placeholder="ค้นหาเลขออเดอร์, ชื่อ, เบอร์โทร" value="<?= e($q) ?>" style="width:240px">
  <button class="btn sm">ค้นหา</button>
  <?php if($q !== ''): ?><a href="<?= url('admin_orders', ['status' => $status]) ?>" class="btn sm gray">รีเซ็ต</a><?php endif; ?>
</form>
<div class="cats">
  <a href="<?= url('admin_orders') ?>" class="<?= $status === '' ? 'active' : '' ?>">ทั้งหมด</a>
  <?php foreach (ORDER_STATUS as $k => $v): ?>
    <a href="<?= e(url('admin_orders', ['status' => $k])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>
<table>
  <tr><th>#</th><th>ลูกค้า</th><th>โทร</th><th>วันที่</th><th class="right">ชิ้น</th><th class="right">ยอดรวม</th><th>ชำระ</th><th>สถานะ</th><th>จัดการ</th></tr>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td>#<?= $o['id'] ?></td><td><?= e($o['customer_name']) ?></td><td><?= e($o['phone']) ?></td>
    <td><?= e($o['created_at']) ?></td><td class="right"><?= (int)$o['items'] ?></td>
    <td class="right"><?= money($o['total']) ?></td>
    <td><?= $o['payment_method'] === 'cod' ? 'ปลายทาง' : 'โอนเงิน' ?></td>
    <td><?= status_badge($o['status']) ?></td>
    <td class="inline">
      <a class="btn sm" href="<?= e(url('admin_order', ['id' => $o['id']])) ?>">ดู</a>
      <a class="btn sm gray" href="<?= e(url('invoice', ['id' => $o['id']])) ?>" target="_blank">พิมพ์</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="9">ไม่มีคำสั่งซื้อ</td></tr><?php endif; ?>
</table>
<?php layout_bottom(true);
}

function admin_order(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) { flash('ไม่พบคำสั่งซื้อ', 'error'); go('admin_orders'); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new = $_POST['status'] ?? '';
        if (!array_key_exists($new, ORDER_STATUS)) {
            flash('สถานะไม่ถูกต้อง', 'error');
        } else {
            $pdo->beginTransaction();
            if ($new === 'cancelled' && $o['status'] !== 'cancelled') {
                $pdo->prepare('UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock + i.qty WHERE i.order_id = ?')->execute([$id]);
            } elseif ($o['status'] === 'cancelled' && $new !== 'cancelled') {
                $pdo->prepare('UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock - i.qty WHERE i.order_id = ?')->execute([$id]);
            }
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$new, $id]);
            $pdo->commit();
            flash('อัปเดตสถานะคำสั่งซื้อเรียบร้อย');
        }
        go('admin_order', ['id' => $id]);
    }
    $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?'); $st->execute([$id]);
    $items = $st->fetchAll();
    layout_top('คำสั่งซื้อ #' . $id, true); ?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap">
  <p><a href="<?= url('admin_orders') ?>">← กลับรายการคำสั่งซื้อ</a></p>
  <a href="<?= e(url('invoice', ['id' => $o['id']])) ?>" target="_blank" class="btn gray">🖨️ พิมพ์ใบเสร็จ</a>
</div>
<h2>คำสั่งซื้อ #<?= $o['id'] ?> <?= status_badge($o['status']) ?></h2>
<div class="stats">
  <div class="stat">
    <h3>ข้อมูลลูกค้า</h3>
    <p><strong><?= e($o['customer_name']) ?></strong><br>📞 <?= e($o['phone']) ?></p>
    <p><?= nl2br(e($o['address'])) ?></p>
    <?php if ($o['note']): ?><p><b>หมายเหตุ:</b> <?= e($o['note']) ?></p><?php endif; ?>
  </div>
  <div class="stat">
    <h3>จัดการสถานะ</h3>
    <p>ชำระเงิน: <?= $o['payment_method'] === 'cod' ? 'เก็บเงินปลายทาง' : 'โอนเงินผ่านธนาคาร' ?><br>วันที่สั่ง: <?= e($o['created_at']) ?></p>
    <form method="post" class="inline">
      <select name="status" style="width:auto">
        <?php foreach (ORDER_STATUS as $k => $v): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <button class="btn">อัปเดตสถานะ</button>
    </form>
    <?php if (!empty($o['slip_image']) && is_file(UPLOAD_DIR . $o['slip_image'])): ?>
      <p style="margin-top:10px"><b>หลักฐานการโอน:</b><br><a href="uploads/<?= e($o['slip_image']) ?>" target="_blank"><img src="uploads/<?= e($o['slip_image']) ?>" style="max-width:120px;border-radius:4px;margin-top:4px"></a></p>
    <?php endif; ?>
  </div>
</div>
<table>
  <tr><th>สินค้า</th><th class="right">ราคา</th><th class="right">จำนวน</th><th class="right">รวม</th></tr>
  <?php foreach ($items as $it): ?>
  <tr><td><?= e($it['product_name']) ?></td><td class="right"><?= money($it['price']) ?></td>
      <td class="right"><?= $it['qty'] ?></td><td class="right"><?= money($it['price'] * $it['qty']) ?></td></tr>
  <?php endforeach; ?>
  <tr><td colspan="3" class="right">ยอดสินค้า</td><td class="right"><?= money($o['subtotal']) ?></td></tr>
  <tr><td colspan="3" class="right">ค่าจัดส่ง</td><td class="right"><?= money($o['shipping']) ?></td></tr>
  <tr><th colspan="3" class="right">ยอดรวมทั้งสิ้น</th><th class="right"><?= money($o['total']) ?></th></tr>
</table>
<?php layout_bottom(true);
}

function admin_customers(PDO $pdo) {
    $customers = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS total_orders, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status<>\'cancelled\') AS total_spent FROM customers c ORDER BY c.id DESC')->fetchAll();
    layout_top('สมาชิกลูกค้า', true); ?>
<h2>👥 จัดการสมาชิกลูกค้า (<?= count($customers) ?> คน)</h2>
<table>
  <tr><th>#</th><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>เบอร์โทร</th><th>ที่อยู่</th><th class="right">คำสั่งซื้อ</th><th class="right">ยอดซื้อรวม</th></tr>
  <?php foreach ($customers as $c): ?>
  <tr>
    <td>#<?= $c['id'] ?></td>
    <td><b><?= e($c['name']) ?></b><br><small><?= e($c['created_at']) ?></small></td>
    <td><?= e($c['email']) ?></td>
    <td><?= e($c['phone']) ?></td>
    <td><?= e($c['address']) ?></td>
    <td class="right"><?= $c['total_orders'] ?> ครั้ง</td>
    <td class="right"><?= money($c['total_spent']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$customers): ?><tr><td colspan="7">ยังไม่มีสมาชิกในระบบ</td></tr><?php endif; ?>
</table>
<?php layout_bottom(true);
}

function admin_reports(PDO $pdo) {
    $summary = $pdo->query('SELECT status, COUNT(*) AS count, COALESCE(SUM(total),0) AS sum FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $topProducts = $pdo->query('SELECT product_name, SUM(qty) AS total_qty, SUM(price*qty) AS total_sales FROM order_items GROUP BY product_name ORDER BY total_qty DESC LIMIT 10')->fetchAll();
    layout_top('รายงานยอดขาย', true); ?>
<h2>📈 รายงานและสถิติยอดขาย</h2>
<div class="stats">
  <?php foreach (ORDER_STATUS as $k => $label): ?>
    <div class="stat">
      <?= e($label) ?>
      <b><?= (int)($summary[$k] ?? 0) ?> รายการ</b>
    </div>
  <?php endforeach; ?>
</div>

<h3>🏆 สินค้าขายดี 10 อันดับแรก</h3>
<table>
  <tr><th>สินค้า</th><th class="right">จำนวนที่ขายได้</th><th class="right">ยอดขายรวม</th></tr>
  <?php foreach ($topProducts as $tp): ?>
  <tr>
    <td><?= e($tp['product_name']) ?></td>
    <td class="right"><?= number_format($tp['total_qty']) ?> ชิ้น</td>
    <td class="right"><?= money($tp['total_sales']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$topProducts): ?><tr><td colspan="3">ยังไม่มีข้อมูลการขาย</td></tr><?php endif; ?>
</table>
<?php layout_bottom(true);
}

function admin_settings(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'password') {
            $st = $pdo->prepare('SELECT password FROM admins WHERE id = ?'); $st->execute([$_SESSION['admin_id']]);
            $hash = $st->fetchColumn();
            $new = $_POST['new'] ?? '';
            if (!$hash || !password_verify($_POST['current'] ?? '', $hash)) flash('รหัสผ่านปัจจุบันไม่ถูกต้อง', 'error');
            elseif (strlen($new) < 6) flash('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร', 'error');
            elseif ($new !== ($_POST['confirm'] ?? '')) flash('ยืนยันรหัสผ่านไม่ตรงกัน', 'error');
            else {
                $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
                flash('เปลี่ยนรหัสผ่านเรียบร้อย');
            }
        } elseif ($action === 'add_admin') {
            $user = trim($_POST['new_user'] ?? '');
            $pass = $_POST['new_pass'] ?? '';
            if ($user === '' || strlen($pass) < 6) {
                flash('ชื่อผู้ใช้ต้องไม่ว่าง และรหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร', 'error');
            } else {
                $st = $pdo->prepare('SELECT id FROM admins WHERE username = ?'); $st->execute([$user]);
                if ($st->fetch()) {
                    flash('ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว', 'error');
                } else {
                    $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)')->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
                    flash('เพิ่มผู้ดูแลระบบสำเร็จ');
                }
            }
        } elseif ($action === 'delete_admin') {
            $aid = (int)($_POST['admin_id'] ?? 0);
            if ($aid === $_SESSION['admin_id']) {
                flash('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้', 'error');
            } elseif ($pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() <= 1) {
                flash('ต้องมีบัญชีแอดมินเหลืออย่างน้อย 1 บัญชี', 'error');
            } else {
                $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$aid]);
                flash('ลบบัญชีผู้ดูแลระบบเรียบร้อย');
            }
        } elseif ($action === 'reset') {
            if (($_POST['confirm_text'] ?? '') !== 'RESET') {
                flash('พิมพ์คำว่า RESET ให้ถูกต้องเพื่อยืนยัน', 'error');
            } else {
                foreach (glob(UPLOAD_DIR . '*') ?: [] as $file) @unlink($file);
                drop_tables($pdo);
                install($pdo);
                $_SESSION = [];
                session_regenerate_id(true);
                flash('รีเซ็ตฐานข้อมูลเป็นค่าเริ่มต้นแล้ว (admin / admin123)');
                go('login');
            }
        }
        go('admin_settings');
    }

    $admins = $pdo->query('SELECT * FROM admins ORDER BY id')->fetchAll();
    $counts = [];
    foreach (TABLES as $t) $counts[$t] = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    layout_top('ตั้งค่าและจัดการแอดมิน', true); ?>
<h2>⚙️ ตั้งค่าระบบและจัดการผู้ดูแลระบบ</h2>
<div class="stats">
  <div class="stat">
    <h3>🔑 เปลี่ยนรหัสผ่านของคุณ</h3>
    <form method="post" class="form flat">
      <input type="hidden" name="action" value="password">
      <label>รหัสผ่านปัจจุบัน</label><input type="password" name="current" required>
      <label>รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)</label><input type="password" name="new" required>
      <label>ยืนยันรหัสผ่านใหม่</label><input type="password" name="confirm" required>
      <br><button class="btn" style="margin-top:8px">บันทึกรหัสผ่าน</button>
    </form>
  </div>

  <div class="stat">
    <h3>🛡️ จัดการผู้ดูแลระบบ (Admins)</h3>
    <table>
      <?php foreach ($admins as $ad): ?>
      <tr>
        <td><?= e($ad['username']) ?> <?= $ad['id'] == $_SESSION['admin_id'] ? '(คุณ)' : '' ?></td>
        <td class="right">
          <?php if (count($admins) > 1 && $ad['id'] != $_SESSION['admin_id']): ?>
          <form method="post" onsubmit="return confirm('ลบแอดมินคนนี้?')">
            <input type="hidden" name="action" value="delete_admin">
            <input type="hidden" name="admin_id" value="<?= $ad['id'] ?>">
            <button class="btn sm red">ลบ</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" class="form flat" style="margin-top:10px">
      <input type="hidden" name="action" value="add_admin">
      <label>เพิ่มแอดมินใหม่</label>
      <input name="new_user" placeholder="ชื่อผู้ใช้" required style="margin-bottom:6px">
      <input type="password" name="new_pass" placeholder="รหัสผ่าน (≥ 6 ตัว)" required style="margin-bottom:6px">
      <button class="btn sm orange">เพิ่มผู้ดูแลระบบ</button>
    </form>
  </div>

  <div class="stat">
    <h3>💾 สำรองข้อมูลฐานข้อมูล</h3>
    <table>
      <?php foreach ($counts as $t => $n): ?><tr><td><?= e($t) ?></td><td class="right"><?= $n ?> แถว</td></tr><?php endforeach; ?>
    </table>
    <a class="btn" href="<?= url('admin_export') ?>">⬇️ ดาวน์โหลดไฟล์สำรอง (.sql)</a>
  </div>

  <div class="stat">
    <h3>♻️ รีเซ็ตระบบใหม่ทั้งหมด</h3>
    <p class="danger">ลบข้อมูลลูกค้า คำสั่งซื้อ สลิป และสินค้าทั้งหมด แล้วคืนค่าเริ่มต้น</p>
    <form method="post" class="form flat" onsubmit="return confirm('ยืนยันการรีเซ็ตข้อมูลทั้งหมด?')">
      <input type="hidden" name="action" value="reset">
      <label>พิมพ์คำว่า RESET เพื่อยืนยัน</label><input name="confirm_text" required>
      <br><button class="btn red" style="margin-top:8px">รีเซ็ตระบบ</button>
    </form>
  </div>
</div>
<?php layout_bottom(true);
}

function admin_export(PDO $pdo) {
    $out  = "-- Aquarium Shop Ultimate Backup " . date('Y-m-d H:i:s') . "\n";
    $out .= "SET NAMES utf8mb4;\nCREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $out .= "USE `" . DB_NAME . "`;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach (TABLES as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        $out .= "\nDROP TABLE IF EXISTS `$t`;\n$create;\n";
        foreach ($pdo->query("SELECT * FROM `$t`") as $row) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row));
            $out .= "INSERT INTO `$t` (`" . implode('`,`', array_keys($row)) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    $out .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="aquarium_shop_ultimate_' . date('Ymd_His') . '.sql"');
    echo $out;
    exit;
}

/* ============================ CSS สไตล์ (Ultimate) ============================ */
function css() {
    return <<<'CSS'
*{box-sizing:border-box}
body{margin:0;font-family:Tahoma,'Sarabun',sans-serif;background:#f0f7fb;color:#1e2a35}
a{color:#0277bd;text-decoration:none}
.container{max-width:1200px;margin:auto;padding:0 16px}
.topbar{background:linear-gradient(90deg,#01579b,#0288d1);position:sticky;top:0;z-index:10}
.nav{display:flex;align-items:center;gap:16px;padding:12px 16px;flex-wrap:wrap}
.logo{color:#fff;font-size:22px;font-weight:700}
.search{flex:1;display:flex;min-width:200px}
.search input{flex:1;padding:8px;border:0;border-radius:6px 0 0 6px}
.search button{border:0;padding:8px 14px;background:#ffb300;border-radius:0 6px 6px 0;cursor:pointer}
nav a{color:#fff;margin-left:12px}
.hero{background:linear-gradient(135deg,#4fc3f7,#0277bd);color:#fff;padding:28px;border-radius:14px;margin:20px 0;text-align:center}
.hero h1{margin:10px 0}
.hero-art{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
.hero-art svg{width:120px;height:90px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.cats{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
.cats a{padding:8px 14px;background:#fff;border-radius:20px;border:1px solid #b3e5fc}
.cats a.active{background:#0277bd;color:#fff}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px}
.card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);display:flex;flex-direction:column;transition:.2s}
.card:hover{transform:translateY(-3px);box-shadow:0 6px 16px rgba(0,0,0,.12)}
.thumb{display:block;aspect-ratio:4/3;background:#e1f5fe;overflow:hidden}
.thumb svg,.thumb img,.detail-img svg,.detail-img img,.mini-thumb svg,.mini-thumb img{width:100%;height:100%;display:block;object-fit:cover}
.card:hover .thumb svg,.card:hover .thumb img{transform:scale(1.05);transition:.3s}
.card-body{padding:12px;display:flex;flex-direction:column;gap:6px;flex:1}
.card-body h3{font-size:16px;margin:0}
.card-body form{margin-top:auto}
.card .btn{width:100%}
.tag{font-size:12px;color:#0277bd;background:#e1f5fe;padding:2px 8px;border-radius:10px;align-self:flex-start;display:inline-block}
.size{font-size:13px;color:#666}
.price{color:#e65100;font-weight:700;font-size:18px}
.price.big{font-size:28px}
.btn{display:inline-block;background:#0288d1;color:#fff;border:0;padding:9px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-family:inherit}
.btn:hover{background:#01579b}
.btn.orange{background:#ef6c00}.btn.red{background:#c62828}.btn.gray{background:#78909c}
.btn.sm{padding:5px 10px;font-size:13px}
.btn.disabled{background:#bbb;cursor:not-allowed;width:100%}
.alert{background:#e8f5e9;border-left:4px solid #43a047;padding:12px;margin:16px 0;border-radius:6px}
.alert.error{background:#ffebee;border-color:#e53935}
.detail{display:grid;grid-template-columns:1fr 1fr;gap:30px;background:#fff;padding:24px;border-radius:12px;margin:20px 0}
.detail-img{border-radius:12px;overflow:hidden;aspect-ratio:4/3}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;margin-bottom:20px}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
th{background:#e1f5fe}
input,select,textarea{padding:8px;border:1px solid #ccc;border-radius:6px;font:inherit;width:100%}
input[type=number]{width:90px}
input[type=color]{width:60px;height:38px;padding:2px}
.inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.inline input:not([type=number]):not([type=color]){width:auto}
.form{background:#fff;padding:20px;border-radius:12px;max-width:640px}
.form.flat{padding:0;max-width:none;background:none}
.form label{display:block;margin:12px 0 4px;font-weight:600}
.box{background:#fff;padding:20px;border-radius:12px;margin:20px 0}
.center{text-align:center}
.footer{text-align:center;padding:24px;color:#607d8b;margin-top:40px}
.danger{color:#c62828;font-weight:600}
.right{text-align:right}
.badge{padding:3px 10px;border-radius:12px;font-size:12px;color:#fff;white-space:nowrap}
.st-pending{background:#ffa000}.st-paid{background:#1e88e5}.st-shipped{background:#8e24aa}.st-completed{background:#43a047}.st-cancelled{background:#9e9e9e}
body.admin{display:flex;min-height:100vh}
.sidebar{width:230px;background:#01579b;color:#fff;padding:16px;flex-shrink:0}
.sidebar .logo{margin-bottom:6px;display:block}
.sidebar a{display:block;color:#fff;padding:10px;border-radius:8px;margin-top:4px}
.sidebar a:hover{background:rgba(255,255,255,.15)}
.admin-main{flex:1;padding:24px;overflow-x:auto}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px}
.stat{background:#fff;padding:18px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.stat b{display:block;font-size:26px;color:#0277bd}
.stat table{box-shadow:none}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
.toolbar input,.toolbar select{width:auto}
.mini-thumb{width:56px;height:42px;border-radius:6px;overflow:hidden}
.login-box{max-width:380px;margin:60px auto;background:#fff;padding:30px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.1)}
@media(max-width:768px){.detail{grid-template-columns:1fr}body.admin{flex-direction:column}.sidebar{width:100%}}
CSS;
}

/* ============================ Router เลือกหน้าจอ ============================ */
$routes = [
    'home' => 'page_home', 'product' => 'page_product', 'cart' => 'page_cart', 'checkout' => 'page_checkout',
    'success' => 'page_success', 'track' => 'page_track', 'invoice' => 'page_invoice',
    'customer_login' => 'page_customer_login', 'customer_logout' => 'page_customer_logout', 'customer_profile' => 'page_customer_profile',
    'login' => 'page_login', 'logout' => 'page_logout',
    'admin' => 'admin_dashboard', 'admin_products' => 'admin_products', 'admin_product_form' => 'admin_product_form',
    'admin_categories' => 'admin_categories', 'admin_orders' => 'admin_orders', 'admin_order' => 'admin_order',
    'admin_customers' => 'admin_customers', 'admin_reports' => 'admin_reports',
    'admin_settings' => 'admin_settings', 'admin_export' => 'admin_export',
];
$page = $_GET['page'] ?? 'home';
if (!isset($routes[$page])) $page = 'home';
if (strpos($page, 'admin') === 0 && empty($_SESSION['admin_id'])) go('login');
$routes[$page]($pdo);

