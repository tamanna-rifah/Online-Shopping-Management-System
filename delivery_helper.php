<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Delivery Tracking Helper
|--------------------------------------------------------------------------
*/

function delivery_status_label(string $status): string
{
    return match ($status) {
        'order_placed'     => 'Order Placed',
        'processing'       => 'Processing',
        'packed'           => 'Packed',
        'shipped'          => 'Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'cancelled'        => 'Cancelled',
        default            => ucfirst(str_replace('_', ' ', $status)),
    };
}

function delivery_statuses(): array
{
    return [
        'order_placed'     => 'Order Placed',
        'processing'       => 'Processing',
        'packed'           => 'Packed',
        'shipped'          => 'Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'cancelled'        => 'Cancelled',
    ];
}

function delivery_status_class(string $status): string
{
    return match ($status) {
        'order_placed'     => 'placed',
        'processing'       => 'processing',
        'packed'           => 'packed',
        'shipped'          => 'shipped',
        'out_for_delivery' => 'out',
        'delivered'        => 'delivered',
        'cancelled'        => 'cancelled',
        default            => 'processing',
    };
}

/*
|--------------------------------------------------------------------------
| Add tracking history
|--------------------------------------------------------------------------
*/

function add_tracking_event(
    mysqli $conn,
    int $orderId,
    string $status,
    ?string $note = null,
    ?string $trackingNumber = null,
    ?string $courierName = null
): bool {

    $stmt = $conn->prepare("
        INSERT INTO order_tracking
        (
            order_id,
            status,
            note,
            tracking_number,
            courier_name,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'issss',
        $orderId,
        $status,
        $note,
        $trackingNumber,
        $courierName
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}