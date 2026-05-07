<?php
session_start();
require_once 'db_connect.php';
require_once 'mail_helper.php';
$resultCode = $_GET['resultCode'] ?? '';
$message    = $_GET['message'] ?? '';
$extraData  = $_GET['extraData'] ?? '';

$internalOrderId = '';

if ($extraData !== '') {
    $decoded = json_decode(base64_decode($extraData), true);

    if (is_array($decoded) && !empty($decoded['internalOrderId'])) {
        $internalOrderId = $decoded['internalOrderId'];
    }
}

if ($internalOrderId === '' && !empty($_GET['orderId'])) {
    $internalOrderId = $_GET['orderId'];
}

try {
    if ($resultCode === '0') {
    $stmt = $pdo->prepare("
        UPDATE `Order`
        SET PaymentStatus = 'Completed',
            Status = 'Đã xác nhận'
        WHERE OrderID = :oid
    ");
    
    $stmt->execute([':oid' => $internalOrderId]);
    sendOrderSuccessMail($pdo, $internalOrderId);
    header(
        'Location: account-index.php?section=tracking'
        . '&payment=success'
        . '&orderId=' . urlencode($internalOrderId)
        . '&msg=' . urlencode('Thanh toán đơn hàng thành công.')
    );
    exit;
} else {
        $stmt = $pdo->prepare("
            UPDATE `Order`
            SET PaymentStatus = 'Cancelled',
                Status = 'Bị hủy'
            WHERE OrderID = :oid
              AND PaymentStatus <> 'Paid'
        ");
        $stmt->execute([':oid' => $internalOrderId]);

        header(
            'Location: account-index.php?section=tracking'
            . '&payment=cancel'
            . '&orderId=' . urlencode($internalOrderId)
            . '&msg=' . urlencode($message !== '' ? $message : 'Bạn đã hủy hoặc thanh toán thất bại.')
        );
        exit;
    }
} catch (Exception $e) {
    header(
        'Location: account-index.php?section=tracking'
        . '&payment=error'
        . '&orderId=' . urlencode($internalOrderId)
        . '&msg=' . urlencode($e->getMessage())
    );
    exit;
}
?>