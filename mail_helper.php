<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendOrderSuccessMail(PDO $pdo, string $orderId): bool
{
    // Lấy thông tin đơn + email khách
    $stmt = $pdo->prepare("
        SELECT 
            o.OrderID,
            o.TotalAmount,
            o.TotalAmountAfterVoucher,
            o.PaymentMethod,
            o.PaymentStatus,
            o.Status,
            o.CreatedDate,
            o.ShippingCity,
            o.ShippingDistrict,
            o.ShippingWard,
            o.ShippingStreet,
            o.ShippingNumber,
            u.FullName,
            u.Email
        FROM `Order` o
        JOIN User_Account u ON u.UserID = o.UserID
        WHERE o.OrderID = :oid
        LIMIT 1
    ");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || empty($order['Email'])) {
        return false;
    }

    // Lấy sản phẩm trong đơn
    $itemStmt = $pdo->prepare("
        SELECT 
            p.ProductName,
            s.Format,
            oi.Quantity,
            oi.UnitPrice,
            oi.DiscountedPrice,
            oi.TotalPrice
        FROM Order_Items oi
        JOIN SKU s ON s.SKUID = oi.SKU_ID
        JOIN Product p ON p.ProductID = s.ProductID
        WHERE oi.OrderID = :oid
    ");
    $itemStmt->execute([':oid' => $orderId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemsHtml .= '
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['ProductName']) . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['Format'] ?? '') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . (int)$item['Quantity'] . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">' . number_format((float)$item['TotalPrice'], 0, ',', '.') . ' đ</td>
            </tr>
        ';
    }

    $total = !empty($order['TotalAmountAfterVoucher'])
        ? (float)$order['TotalAmountAfterVoucher']
        : (float)$order['TotalAmount'];

    $address = trim(
        ($order['ShippingNumber'] ?? '') . ', ' .
        ($order['ShippingStreet'] ?? '') . ', ' .
        ($order['ShippingWard'] ?? '') . ', ' .
        ($order['ShippingDistrict'] ?? '') . ', ' .
        ($order['ShippingCity'] ?? '')
    );

    $body = '
        <div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">
            <h2 style="color:#1E4A8C;">Moonlit Store xác nhận đơn hàng</h2>

            <p><strong>' . htmlspecialchars($order['FullName']) . '</strong>,</p>

            <p>Đơn hàng của bạn đã được ghi nhận thành công.</p>

            <p>
                <strong>Mã đơn:</strong> ' . htmlspecialchars($order['OrderID']) . '<br>
                <strong>Ngày đặt:</strong> ' . htmlspecialchars($order['CreatedDate']) . '<br>
                <strong>Phương thức thanh toán:</strong> ' . htmlspecialchars($order['PaymentMethod']) . '<br>
                <strong>Trạng thái thanh toán:</strong> ' . htmlspecialchars($order['PaymentStatus']) . '<br>
                <strong>Địa chỉ nhận hàng:</strong> ' . htmlspecialchars($address) . '
            </p>

            <table style="width:100%;border-collapse:collapse;margin-top:15px;">
                <thead>
                    <tr style="background:#F3E9D7;">
                        <th style="padding:8px;text-align:left;">Sản phẩm</th>
                        <th style="padding:8px;text-align:left;">Định dạng</th>
                        <th style="padding:8px;text-align:center;">SL</th>
                        <th style="padding:8px;text-align:right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsHtml . '
                </tbody>
            </table>

            <h3 style="text-align:right;color:#1E4A8C;">
                Tổng thanh toán: ' . number_format($total, 0, ',', '.') . ' đ
            </h3>

            <p>Cảm ơn bạn đã mua sách tại Moonlit Store 🌙</p>
        </div>
    ';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // Đổi thành email gửi của bạn
        $mail->Username   = 'namrombentre@gmail.com';

        // Dùng App Password, không dùng mật khẩu Gmail thường
        $mail->Password   = 'fzkc ppgm ftbd prcy';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom('namrombentre@gmail.com', 'Moonlit');
        $mail->addAddress($order['Email'], $order['FullName']);

        $mail->isHTML(true);
        $mail->Subject = 'Xác nhận đơn hàng #' . $order['OrderID'];
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Send order mail failed: ' . $mail->ErrorInfo);
        return false;
    }
}