<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/storage.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // Collect IDs of payment methods that are in use by this user
    $paymentsInUseQuery = $pdo->prepare('SELECT DISTINCT payment_method_id FROM subscriptions WHERE user_id = :userId');
    $paymentsInUseQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
    $paymentsInUseQuery->execute();
    $paymentsInUse = $paymentsInUseQuery->fetchAll(PDO::FETCH_COLUMN, 0);

    // List payment methods for this user
    $sql = 'SELECT * FROM payment_methods WHERE user_id = :userId ORDER BY "order" ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $payments = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payments[] = $row;
    }

    foreach ($payments as $payment) {
        // Built-in icons remain local path; custom icons go through storage helper
        $iconVal = $payment['icon'];
        if (strpos($iconVal, 'images/uploads/icons/') !== false) {
            $paymentIconFolder = '';
        } else {
            $paymentIconFolder = (storage_driver() === 'supabase') ? '' : 'images/uploads/logos/';
            if (storage_driver() === 'supabase') {
                $iconVal = storage_public_url('logos/' . basename($iconVal));
            }
        }
        $inUse = in_array($payment['id'], $paymentsInUse);
        ?>
        <div class="payments-payment" data-enabled="<?= $payment['enabled']; ?>" data-in-use="<?= $inUse ? 'yes' : 'no' ?>"
            data-paymentid="<?= $payment['id'] ?>"
            title="<?= $inUse ? translate('cant_delete_payment_method_in_use', $i18n) : ($payment['enabled'] ? translate('disable', $i18n) : translate('enable', $i18n)) ?>"
            onClick="togglePayment(<?= $payment['id'] ?>)">
            <img src="<?= $paymentIconFolder . (strpos($payment['icon'], 'images/uploads/icons/') !== false ? $payment['icon'] : (storage_driver() === 'supabase' ? basename($iconVal) === basename($payment['icon']) ? $iconVal : $iconVal : $payment['icon'])) ?>" alt="Logo" />
            <span class="payment-name">
                <?= $payment['name'] ?>
            </span>
            <?php
            if (!$inUse) {
                ?>
                <div class="delete-payment-method" title="<?= translate('delete', $i18n) ?>" data-paymentid="<?= $payment['id'] ?>"
                    onclick="deletePaymentMethod(<?= $payment['id'] ?>)">
                    x
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => translate('error', $i18n)));
    exit();
}

?>
