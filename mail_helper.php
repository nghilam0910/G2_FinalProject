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
        <tr style="background:#ffffff;">
            <td style="padding:13px 12px;border-bottom:1px solid #EFE4C8;line-height:1.5;">
                <strong>' . htmlspecialchars($item['ProductName']) . '</strong>
            </td>
            <td style="padding:13px 12px;border-bottom:1px solid #EFE4C8;color:#555;">
                ' . htmlspecialchars($item['Format'] ?? '') . '
            </td>
            <td style="padding:13px 12px;border-bottom:1px solid #EFE4C8;text-align:center;">
                ' . (int) $item['Quantity'] . '
            </td>
            <td style="padding:13px 12px;border-bottom:1px solid #EFE4C8;text-align:right;font-weight:bold;color:#1E4F95;">
                ' . number_format((float) $item['TotalPrice'], 0, ',', '.') . ' đ
            </td>
        </tr>
    ';
    }

    $total = !empty($order['TotalAmountAfterVoucher'])
        ? (float) $order['TotalAmountAfterVoucher']
        : (float) $order['TotalAmount'];

    $address = trim(
        ($order['ShippingNumber'] ?? '') . ', ' .
        ($order['ShippingStreet'] ?? '') . ', ' .
        ($order['ShippingWard'] ?? '') . ', ' .
        ($order['ShippingDistrict'] ?? '') . ', ' .
        ($order['ShippingCity'] ?? '')
    );

    $body = '
<div style="margin:0;padding:0;background:#F4EEDC;font-family:Arial,Helvetica,sans-serif;color:#2f2f2f;">
    <div style="max-width:760px;margin:0 auto;padding:28px 16px;">

        <div style="background:#1E4F95;border-radius:18px 18px 0 0;padding:24px 28px;text-align:center;">
            <div style="font-size:30px;font-weight:800;letter-spacing:3px;color:#F7D94C;">
                MOONLIT
            </div>
            <div style="font-size:14px;color:#ffffff;margin-top:6px;">
                Cảm ơn bạn đã đặt sách tại Moonlit Store 🌙
            </div>
        </div>

        <div style="background:#ffffff;border-radius:0 0 18px 18px;padding:30px 32px;border:1px solid #E8D8A8;border-top:none;">

            <h2 style="margin:0 0 14px 0;color:#1E4F95;font-size:24px;">
                Xác nhận đơn hàng thành công
            </h2>

            <p style="font-size:15px;line-height:1.7;margin:0 0 16px 0;">
                Xin chào <strong>' . htmlspecialchars($order['FullName']) . '</strong>,
            </p>

            <p style="font-size:15px;line-height:1.7;margin:0 0 22px 0;">
                Moonlit Store đã ghi nhận đơn hàng của bạn. Dưới đây là thông tin chi tiết đơn hàng.
            </p>

            <div style="background:#F8F3E7;border:1px solid #E9D8A6;border-radius:14px;padding:18px 20px;margin-bottom:24px;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr>
                        <td style="padding:6px 0;color:#666;width:190px;">Mã đơn hàng</td>
                        <td style="padding:6px 0;font-weight:bold;color:#1E4F95;">#' . htmlspecialchars($order['OrderID']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#666;">Ngày đặt</td>
                        <td style="padding:6px 0;">' . htmlspecialchars($order['CreatedDate']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#666;">Phương thức thanh toán</td>
                        <td style="padding:6px 0;">' . htmlspecialchars($order['PaymentMethod']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#666;">Trạng thái thanh toán</td>
                        <td style="padding:6px 0;font-weight:bold;color:#D99A00;">' . htmlspecialchars($order['PaymentStatus']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#666;vertical-align:top;">Địa chỉ nhận hàng</td>
                        <td style="padding:6px 0;line-height:1.5;">' . htmlspecialchars($address) . '</td>
                    </tr>
                </table>
            </div>

            <h3 style="margin:0 0 12px 0;color:#1E4F95;font-size:18px;">
                Sản phẩm đã đặt
            </h3>

            <table style="width:100%;border-collapse:collapse;font-size:14px;border:1px solid #E9D8A6;border-radius:12px;overflow:hidden;">
                <thead>
                    <tr style="background:#1E4F95;color:#ffffff;">
                        <th style="padding:12px;text-align:left;">Sản phẩm</th>
                        <th style="padding:12px;text-align:left;">Định dạng</th>
                        <th style="padding:12px;text-align:center;">SL</th>
                        <th style="padding:12px;text-align:right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsHtml . '
                </tbody>
            </table>

            <div style="text-align:right;margin-top:22px;">
                <div style="display:inline-block;background:#F8F3E7;border:1px solid #E9D8A6;border-radius:14px;padding:16px 22px;">
                    <div style="font-size:13px;color:#666;margin-bottom:4px;">Tổng thanh toán</div>
                    <div style="font-size:24px;font-weight:800;color:#1E4F95;">
                        ' . number_format($total, 0, ',', '.') . ' đ
                    </div>
                </div>
            </div>

            <div style="margin-top:28px;padding:18px 20px;background:#F8F3E7;border-radius:14px;text-align:center;">
                <p style="margin:0;font-size:15px;line-height:1.6;">
                    Moonlit sẽ sớm xử lý đơn hàng và giao sách đến bạn trong thời gian gần nhất.
                </p>
                <p style="margin:8px 0 0 0;color:#1E4F95;font-weight:bold;">
                    Chúc bạn có những phút giây đọc sách thật dễ chịu 🌙
                </p>
            </div>

        </div>

        <div style="text-align:center;font-size:12px;color:#777;margin-top:16px;">
            Email này được gửi tự động từ Moonlit Store. Vui lòng không trả lời email này.
        </div>

    </div>
</div>
';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // Đổi thành email gửi của bạn
        $mail->Username = 'namrombentre@gmail.com';

        // Dùng App Password, không dùng mật khẩu Gmail thường
        $mail->Password = 'fzkc ppgm ftbd prcy';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom('namrombentre@gmail.com', 'Moonlit');
        $mail->addAddress($order['Email'], $order['FullName']);

        $mail->isHTML(true);
        $mail->Subject = 'Xác nhận đơn hàng #' . $order['OrderID'];
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Send order mail failed: ' . $mail->ErrorInfo);
        return false;
    }
}