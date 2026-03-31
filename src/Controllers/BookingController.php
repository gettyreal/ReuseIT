<?php
namespace ReuseIT\Controllers;

use InvalidArgumentException;
use ReuseIT\Repositories\BookingEventRepository;
use ReuseIT\Repositories\BookingRepository;
use ReuseIT\Repositories\PickupWindowRepository;
use ReuseIT\Response;
use ReuseIT\Services\BookingService;
use RuntimeException;
use Throwable;

/**
 * BookingController
 *
 * Handles booking REST endpoints and delegates workflow logic to BookingService.
 */
class BookingController {

    private BookingService $bookingService;
    private BookingRepository $bookingRepository;
    private BookingEventRepository $bookingEventRepository;
    private PickupWindowRepository $pickupWindowRepository;

    public function __construct(
        BookingService $bookingService,
        BookingRepository $bookingRepository,
        BookingEventRepository $bookingEventRepository,
        PickupWindowRepository $pickupWindowRepository
    ) {
        $this->bookingService = $bookingService;
        $this->bookingRepository = $bookingRepository;
        $this->bookingEventRepository = $bookingEventRepository;
        $this->pickupWindowRepository = $pickupWindowRepository;
    }

    public function create(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $userId = (int) $_SESSION['user_id'];
        $input = $this->parseJsonInput();
        $listingId = (int) ($input['listing_id'] ?? 0);

        if ($listingId <= 0) {
            return Response::validationErrors([
                ['field' => 'listing_id', 'message' => 'listing_id is required and must be a positive integer']
            ], 400);
        }

        try {
            $result = $this->bookingService->createBooking($listingId, $userId);
            return Response::success($result, 201);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function list(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $userId = (int) $_SESSION['user_id'];
        $role = isset($get['role']) ? (string) $get['role'] : 'seller';
        $status = isset($get['status']) && $get['status'] !== '' ? (string) $get['status'] : null;
        $limit = isset($get['limit']) ? (int) $get['limit'] : 20;
        $offset = isset($get['offset']) ? (int) $get['offset'] : 0;

        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        try {
            $result = $this->bookingService->listBookingsByRole($userId, $role, $status, $limit, $offset);
            return Response::success($result, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function show(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $userId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return Response::error('Booking not found', 404);
        }

        if (!$this->bookingRepository->isParticipant($bookingId, $userId)) {
            return Response::error('Forbidden', 403);
        }

        $events = $this->bookingEventRepository->findByBookingId($bookingId);
        $activePickupWindow = $this->pickupWindowRepository->findLatestAcceptedByBookingId($bookingId);

        return Response::success([
            'booking' => $booking,
            'timeline' => $events,
            'active_pickup_window' => $activePickupWindow,
        ], 200);
    }

    public function confirm(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $sellerId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        try {
            $booking = $this->bookingService->confirmBooking($bookingId, $sellerId);
            return Response::success($booking, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function complete(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        try {
            $booking = $this->bookingService->completeBooking($bookingId, $actorUserId);
            return Response::success($booking, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function cancel(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $reasonCode = trim((string) ($input['reason_code'] ?? ''));
        $note = isset($input['note']) ? trim((string) $input['note']) : null;

        if ($reasonCode === '') {
            return Response::validationErrors([
                ['field' => 'reason_code', 'message' => 'reason_code is required']
            ], 400);
        }

        try {
            $booking = $this->bookingService->cancelBooking($bookingId, $actorUserId, $reasonCode, $note);
            return Response::success($booking, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function proposePickup(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $buyerId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $startAt = (string) ($input['window_start_at'] ?? '');
        $endAt = (string) ($input['window_end_at'] ?? '');

        if ($startAt === '' || $endAt === '') {
            return Response::validationErrors([
                ['field' => 'window_start_at', 'message' => 'window_start_at is required'],
                ['field' => 'window_end_at', 'message' => 'window_end_at is required']
            ], 400);
        }

        try {
            $result = $this->bookingService->proposePickupWindow($bookingId, $buyerId, $startAt, $endAt);
            return Response::success($result, 201);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function counterPickup(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $sellerId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $startAt = (string) ($input['window_start_at'] ?? '');
        $endAt = (string) ($input['window_end_at'] ?? '');

        if ($startAt === '' || $endAt === '') {
            return Response::validationErrors([
                ['field' => 'window_start_at', 'message' => 'window_start_at is required'],
                ['field' => 'window_end_at', 'message' => 'window_end_at is required']
            ], 400);
        }

        try {
            $result = $this->bookingService->counterPickupWindow($bookingId, $sellerId, $startAt, $endAt);
            return Response::success($result, 201);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function acceptPickup(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $proposalId = (int) ($input['proposal_id'] ?? 0);

        if ($proposalId <= 0) {
            return Response::validationErrors([
                ['field' => 'proposal_id', 'message' => 'proposal_id is required and must be a positive integer']
            ], 400);
        }

        try {
            $result = $this->bookingService->acceptPickupWindow($bookingId, $actorUserId, $proposalId);
            return Response::success($result, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    private function parseJsonInput(): array {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mapBookingException(Throwable $e): string {
        if ($e instanceof RuntimeException && $e->getCode() === 409) {
            return Response::error($e->getMessage(), 409);
        }

        if ($e instanceof InvalidArgumentException) {
            $message = $e->getMessage();
            $lower = strtolower($message);

            if (strpos($lower, 'not found') !== false) {
                return Response::error($message, 404);
            }

            if (
                strpos($lower, 'only seller') !== false ||
                strpos($lower, 'only buyer') !== false ||
                strpos($lower, 'only booking participants') !== false ||
                strpos($lower, 'cannot book your own listing') !== false
            ) {
                return Response::error($message, 403);
            }

            if (
                strpos($lower, 'invalid booking transition') !== false ||
                strpos($lower, 'already has an active booking') !== false ||
                strpos($lower, 'expired') !== false ||
                strpos($lower, 'only active listings can be booked') !== false
            ) {
                return Response::error($message, 409);
            }

            return Response::error($message, 400);
        }

        return Response::error('Server error', 500);
    }
}
