<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var array<string,mixed>|null $result */
/** @var string $trackingNumber */
$result = $result ?? null;
$trackingNumber = $trackingNumber ?? '';
$shipment = is_array($result) && ($result['found'] ?? false) ? ($result['shipment'] ?? null) : null;
$timeline = is_array($result) && ($result['found'] ?? false) ? ($result['timeline'] ?? []) : [];
$proof = is_array($result) && ($result['found'] ?? false) ? ($result['proof'] ?? null) : null;
$notFound = is_array($result) && !($result['found'] ?? false) && $trackingNumber !== '';
?>
<section class="rateb-portal-section" data-logistics-track>
    <div class="container">
        <h1><?php echo View::escape(__('logistics_portal_title')); ?></h1>
        <p><?php echo View::escape(__('logistics_portal_hint')); ?></p>

        <form class="rateb-portal-form" method="get" action="<?php echo rateb_url('site/customer/logistics'); ?>">
            <div class="rateb-portal-form__field">
                <label for="tracking"><?php echo View::escape(__('logistics_tracking_number')); ?></label>
                <input id="tracking" type="text" name="tracking" value="<?php echo View::escape($trackingNumber); ?>" required maxlength="64" autocomplete="off">
            </div>
            <button type="submit" class="rateb-portal-btn"><?php echo View::escape(__('logistics_track_shipment')); ?></button>
        </form>

        <?php if ($notFound) { ?>
            <p class="rateb-portal-alert"><?php echo View::escape((string) ($result['message'] ?? __('no_records'))); ?></p>
        <?php } ?>

        <?php if (is_array($shipment)) { ?>
            <div class="rateb-portal-card" style="margin-top:1.5rem;">
                <h2><?php echo View::escape(__('logistics_delivery_status')); ?></h2>
                <p>
                    <strong><?php echo View::escape((string) ($shipment['tracking_number'] ?? '')); ?></strong>
                    — <?php echo View::escape((string) ($shipment['status_label'] ?? $shipment['status'] ?? '')); ?>
                </p>
                <?php if (!empty($shipment['pickup_location']) || !empty($shipment['delivery_location'])) { ?>
                    <p>
                        <?php echo View::escape((string) ($shipment['pickup_location'] ?? '')); ?>
                        →
                        <?php echo View::escape((string) ($shipment['delivery_location'] ?? '')); ?>
                    </p>
                <?php } ?>
                <?php if (!empty($shipment['delivered_at'])) { ?>
                    <p><?php echo View::escape(__('logistics_status_delivered')); ?>: <?php echo View::escape((string) $shipment['delivered_at']); ?></p>
                <?php } ?>
            </div>

            <h2><?php echo View::escape(__('logistics_shipment_timeline')); ?></h2>
            <?php if ($timeline === []) { ?>
                <p class="text-muted"><?php echo View::escape(__('logistics_timeline_empty')); ?></p>
            <?php } else { ?>
                <ol class="rateb-svc-timeline">
                    <?php foreach ($timeline as $ev) { ?>
                        <li>
                            <strong><?php echo View::escape((string) ($ev['title'] ?? $ev['to_status'] ?? '')); ?></strong>
                            <span><?php echo View::escape((string) ($ev['created_at'] ?? '')); ?></span>
                            <?php if (!empty($ev['body'])) { ?>
                                <p><?php echo View::escape((string) $ev['body']); ?></p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>

            <h2><?php echo View::escape(__('logistics_proof_of_delivery')); ?></h2>
            <?php if (!is_array($proof)) { ?>
                <p class="text-muted"><?php echo View::escape(__('logistics_pod_missing')); ?></p>
            <?php } else { ?>
                <ul class="rateb-portal-list">
                    <?php if (!empty($proof['receiver_name'])) { ?>
                        <li><?php echo View::escape(__('logistics_receiver_name')); ?>: <?php echo View::escape((string) $proof['receiver_name']); ?></li>
                    <?php } ?>
                    <?php if (!empty($proof['delivered_at'])) { ?>
                        <li><?php echo View::escape(__('logistics_status_delivered')); ?>: <?php echo View::escape((string) $proof['delivered_at']); ?></li>
                    <?php } ?>
                    <?php if (!empty($proof['signature_file'])) { ?>
                        <li><?php echo View::escape(__('logistics_signature')); ?>: <?php echo View::escape((string) $proof['signature_file']); ?></li>
                    <?php } ?>
                    <?php if (!empty($proof['photo_file'])) { ?>
                        <li><?php echo View::escape(__('logistics_photo')); ?>: <?php echo View::escape((string) $proof['photo_file']); ?></li>
                    <?php } ?>
                    <?php if ($proof['gps_lat'] !== null && $proof['gps_long'] !== null) { ?>
                        <li>GPS: <?php echo View::escape((string) $proof['gps_lat']); ?>, <?php echo View::escape((string) $proof['gps_long']); ?></li>
                    <?php } ?>
                    <?php if (!empty($proof['notes'])) { ?>
                        <li><?php echo View::escape((string) $proof['notes']); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        <?php } ?>
    </div>
</section>
