<?php
session_start();
require_once 'db_connect.php';
require_once 'mail_helper.php';
$orderId = trim($_GET['orderId'] ?? '');
$cancel  = isset($_GET['cancel']) && $_GET['cancel'] === '1';

if ($orderId === '') {
    header('Location: account-index.php?section=tracking&payment=error&msg=' . urlencode('Không xác định được mã đơn hàng.'));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT OrderID, UserID, PaymentStatus
        FROM `Order`
        WHERE OrderID = :oid
        FOR UPDATE
    ");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Đơn hàng không tồn tại.');
    }

    if ($order['PaymentStatus'] === 'Completed') {
        $pdo->commit();
        header('Location: account-index.php?section=tracking&payment=success&orderId=' . urlencode($orderId) . '&msg=' . urlencode('Thanh toán đã hoàn tất.'));
        exit;
    }

    if ($cancel) {
        $stmtCancel = $pdo->prepare("
            UPDATE `Order`
            SET PaymentStatus = 'Cancelled', Status = 'Bị hủy'
            WHERE OrderID = :oid AND PaymentStatus <> 'Completed'
        ");
        $stmtCancel->execute([':oid' => $orderId]);

        $pdo->commit();
        header('Location: account-index.php?section=tracking&payment=cancel&orderId=' . urlencode($orderId) . '&msg=' . urlencode('Bạn đã hủy thanh toán PayPal.'));
        exit;
    }

    $itemsStmt = $pdo->prepare("
        SELECT SKU_ID, Quantity
        FROM Order_Items
        WHERE OrderID = :oid
    ");
    $itemsStmt->execute([':oid' => $orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('Đơn hàng không có sản phẩm.');
    }

    $decStock = $pdo->prepare("
        UPDATE SKU
        SET Stock = Stock - :qty_sub
        WHERE SKUID = :skuid
          AND Status = 1
          AND Stock >= :qty_check
    ");

    $incSold = $pdo->prepare("
        UPDATE Product p
        JOIN SKU s ON s.ProductID = p.ProductID
        SET p.SoldQuantity = COALESCE(p.SoldQuantity, 0) + :sold_qty
        WHERE s.SKUID = :skuid
    ");

    foreach ($items as $item) {
        $qty = (int)$item['Quantity'];
        $skuid = $item['SKU_ID'];

        $decStock->execute([
            ':qty_sub'   => $qty,
            ':qty_check' => $qty,
            ':skuid'     => $skuid
        ]);

        if ($decStock->rowCount() <= 0) {
            throw new Exception('Không đủ tồn kho cho SKU ' . $skuid);
        }

        $incSold->execute([
            ':sold_qty' => $qty,
            ':skuid'    => $skuid
        ]);
    }

    $updateOrder = $pdo->prepare("
        UPDATE `Order`
        SET PaymentStatus = 'Completed',
            Status = 'Chờ xác nhận'
        WHERE OrderID = :oid
    ");
    $updateOrder->execute([':oid' => $orderId]);

    $pdo->commit();
    sendOrderSuccessMail($pdo, $orderId);
    header('Location: account-index.php?section=tracking&payment=success&orderId=' . urlencode($orderId) . '&msg=' . urlencode('Thanh toán PayPal thành công.'));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: account-index.php?section=tracking&payment=error&orderId=' . urlencode($orderId) . '&msg=' . urlencode('Lỗi xử lý PayPal: ' . $e->getMessage()));
    exit;
}