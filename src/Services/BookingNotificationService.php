<?php
namespace ReuseIT\Services;

/**
 * BookingNotificationService
 *
 * Lightweight app-level notification dispatcher for booking lifecycle events.
 * Uses error_log for now (no external provider) with structured JSON payloads.
 */
class BookingNotificationService {

    /**
     * Notify participants that a booking expired.
     */
    public function notifyBookingExpired(array $booking): void {
        $payload = [
            'type' => 'booking_expired',
            'booking_id' => (int) ($booking['id'] ?? 0),
            'listing_id' => (int) ($booking['listing_id'] ?? 0),
            'buyer_id' => (int) ($booking['buyer_id'] ?? 0),
            'seller_id' => (int) ($booking['seller_id'] ?? 0),
            'reason' => 'expired',
            'event_at' => date('Y-m-d H:i:s')
        ];

        error_log('[booking-notification] ' . json_encode($payload));
    }

    /**
     * Notify participants that a booking was cancelled.
     */
    public function notifyBookingCancelled(array $booking, string $reasonCode, ?string $note): void {
        $payload = [
            'type' => 'booking_cancelled',
            'booking_id' => (int) ($booking['id'] ?? 0),
            'listing_id' => (int) ($booking['listing_id'] ?? 0),
            'buyer_id' => (int) ($booking['buyer_id'] ?? 0),
            'seller_id' => (int) ($booking['seller_id'] ?? 0),
            'reason' => $reasonCode,
            'note' => $note,
            'event_at' => date('Y-m-d H:i:s')
        ];

        error_log('[booking-notification] ' . json_encode($payload));
    }
}
