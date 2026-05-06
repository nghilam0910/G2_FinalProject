<?php
session_start();
require_once 'db_connect.php';

$orderId = trim($_GET['orderId'] ?? '');
if ($orderId === '') {
    header('Location: checkout.php?error=' . urlencode('Không tìm thấy mã đơn hàng PayPal.'));
    exit;
}

$stmt = $pdo->prepare("SELECT OrderID, TotalAmountAfterVoucher, PaymentStatus FROM `Order` WHERE OrderID = :oid LIMIT 1");
$stmt->execute([':oid' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: checkout.php?error=' . urlencode('Đơn hàng không tồn tại.'));
    exit;
}

if ($order['PaymentStatus'] !== 'Pending') {
    header('Location: account-index.php?section=tracking&payment=error&orderId=' . urlencode($orderId) . '&msg=' . urlencode('Đơn hàng không ở trạng thái chờ thanh toán.'));
    exit;
}

$businessEmail = 'sb-c8jgx50940429@business.example.com';
$paypalUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
$amountUSD = $order['TotalAmountAfterVoucher'] / 24000;
$amount = number_format($amountUSD, 2, '.', '');
$itemName = 'Thanh toán đơn hàng ' . $orderId;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $scheme . '://' . $host . $scriptDir;

$returnUrl = $baseUrl . '/paypal_return.php?orderId=' . urlencode($orderId);
$cancelUrl = $baseUrl . '/paypal_return.php?orderId=' . urlencode($orderId) . '&cancel=1';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chuyển đến PayPal...</title>
</head>
<body>
    <p>Chuyển bạn đến PayPal để hoàn tất thanh toán...</p>
    <form id="paypalForm" action="<?php echo htmlspecialchars($paypalUrl); ?>" method="post">
        <input type="hidden" name="cmd" value="_xclick">
        <input type="hidden" name="business" value="<?php echo htmlspecialchars($businessEmail); ?>">
        <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($itemName); ?>">
        <input type="hidden" name="item_number" value="<?php echo htmlspecialchars($orderId); ?>">
        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
        <input type="hidden" name="currency_code" value="USD">
        <input type="hidden" name="invoice" value="<?php echo htmlspecialchars($orderId); ?>">
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl); ?>">
        <input type="hidden" name="cancel_return" value="<?php echo htmlspecialchars($cancelUrl); ?>">
        <input type="hidden" name="rm" value="2">
        <?php // Không gửi notify_url khi chạy local / sandbox nếu callback không public ?>
        <input type="hidden" name="charset" value="UTF-8">
        <input type="hidden" name="no_note" value="1">
        <input type="hidden" name="no_shipping" value="1">
        <button type="submit">Thanh toán với PayPal</button>
    </form>
    <script>document.getElementById('paypalForm').submit();</script>
</body>
</html>
