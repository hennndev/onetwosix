<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\BankAccount;
use App\Models\Billing;
use App\Models\CustomerKeep;
use App\Models\CustomerUser;
use App\Models\DisplayMessageRequest;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promo;
use App\Models\QrisSetting;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\SongRequest;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\WhatsappSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApiDemoSeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'mobile.demo@126club.test';

    public const CUSTOMER_PHONE = '081266660126';

    public const CUSTOMER_PASSWORD = 'password';

    public function run(): void
    {
        DB::transaction(function (): void {
            $tiers = $this->seedTiers();
            $areas = $this->seedAreasAndTables();
            $customers = $this->seedCustomers($tiers);
            $staff = $this->seedStaff();

            $this->seedPublicContent($areas['lounge']);
            $this->seedPaymentInformation();
            $this->seedRewardsAndBottles($customers['active']);
            $this->seedBookingHistory(
                $customers['active'],
                $staff,
                $areas,
            );
        });

        $this->command?->info('API demo data siap. Login: '.self::CUSTOMER_EMAIL.' / '.self::CUSTOMER_PASSWORD);
    }

    /**
     * @return array<string, Tier>
     */
    private function seedTiers(): array
    {
        $registered = Tier::updateOrCreate(['level' => 1], [
            'name' => 'Registered',
            'discount_percentage' => 0,
            'minimum_spent' => 0,
            'is_first_tier' => true,
            'color' => 'slate',
        ]);

        $recognized = Tier::updateOrCreate(['level' => 2], [
            'name' => 'Recognized',
            'discount_percentage' => 5,
            'minimum_spent' => 5_000_000,
            'is_first_tier' => false,
            'color' => 'blue',
        ]);

        $untouchable = Tier::updateOrCreate(['level' => 3], [
            'name' => 'Untouchable',
            'discount_percentage' => 10,
            'minimum_spent' => 25_000_000,
            'is_first_tier' => false,
            'color' => 'amber',
        ]);

        return compact('registered', 'recognized', 'untouchable');
    }

    /**
     * @return array{lounge: Area, vip_area: Area, active: Tabel, available: Tabel, vip: Tabel}
     */
    private function seedAreasAndTables(): array
    {
        $lounge = Area::updateOrCreate(['code' => 'LNG'], [
            'name' => 'Main Lounge',
            'capacity' => 80,
            'description' => 'Area lounge utama untuk data demo mobile API.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $vipArea = Area::updateOrCreate(['code' => 'ROOM'], [
            'name' => 'VIP Room',
            'capacity' => 24,
            'description' => 'Private room untuk data demo booking.',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $activeTable = Tabel::updateOrCreate([
            'area_id' => $lounge->id,
            'table_number' => 'API Lounge 01',
        ], [
            'qr_code' => 'API-DEMO-TABLE-LNG-01',
            'capacity' => 6,
            'minimum_charge' => 1_000_000,
            'status' => 'occupied',
            'is_active' => true,
            'notes' => 'Dipakai oleh sesi aktif customer demo.',
            'position_x' => 20,
            'position_y' => 30,
        ]);

        $availableTable = Tabel::updateOrCreate([
            'area_id' => $lounge->id,
            'table_number' => 'API Lounge 02',
        ], [
            'qr_code' => 'API-DEMO-TABLE-LNG-02',
            'capacity' => 4,
            'minimum_charge' => 750_000,
            'status' => 'available',
            'is_active' => true,
            'notes' => 'Meja tersedia untuk mencoba endpoint booking.',
            'position_x' => 45,
            'position_y' => 30,
        ]);

        $vipTable = Tabel::updateOrCreate([
            'area_id' => $vipArea->id,
            'table_number' => 'API VIP 01',
        ], [
            'qr_code' => 'API-DEMO-TABLE-VIP-01',
            'capacity' => 10,
            'minimum_charge' => 3_000_000,
            'status' => 'available',
            'is_active' => true,
            'notes' => 'VIP room demo.',
            'position_x' => 15,
            'position_y' => 15,
        ]);

        return [
            'lounge' => $lounge,
            'vip_area' => $vipArea,
            'active' => $activeTable,
            'available' => $availableTable,
            'vip' => $vipTable,
        ];
    }

    /**
     * @param  array<string, Tier>  $tiers
     * @return array<string, CustomerUser>
     */
    private function seedCustomers(array $tiers): array
    {
        $active = $this->seedCustomer([
            'name' => 'Mobile API Demo',
            'email' => self::CUSTOMER_EMAIL,
            'phone' => self::CUSTOMER_PHONE,
            'birth_date' => '1995-06-12',
            'address' => 'Jakarta Selatan',
            'accurate_id' => 9_900_126,
            'customer_code' => 'API-DEMO-001',
            'total_visits' => 18,
            'lifetime_spending' => 20_000_000,
            'tier_id' => $tiers['recognized']->id,
            'token_firebase' => 'demo-firebase-device-token',
        ]);

        $topSpender = $this->seedCustomer([
            'name' => 'API Top Spender',
            'email' => 'top.spender@126club.test',
            'phone' => '081266660127',
            'birth_date' => '1990-01-20',
            'address' => 'Jakarta Pusat',
            'accurate_id' => 9_900_127,
            'customer_code' => 'API-DEMO-002',
            'total_visits' => 42,
            'lifetime_spending' => 75_000_000,
            'tier_id' => $tiers['untouchable']->id,
        ]);

        $newCustomer = $this->seedCustomer([
            'name' => 'API New Member',
            'email' => 'new.member@126club.test',
            'phone' => '081266660128',
            'birth_date' => '2000-10-10',
            'address' => 'Tangerang',
            'accurate_id' => 9_900_128,
            'customer_code' => 'API-DEMO-003',
            'total_visits' => 2,
            'lifetime_spending' => 1_250_000,
            'tier_id' => $tiers['registered']->id,
        ]);

        return compact('active', 'topSpender', 'newCustomer');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedCustomer(array $attributes): CustomerUser
    {
        $user = User::updateOrCreate(['email' => $attributes['email']], [
            'name' => $attributes['name'],
            'email_verified_at' => now(),
            'password' => Hash::make(self::CUSTOMER_PASSWORD),
            'type' => 'customer',
            'token_firebase' => $attributes['token_firebase'] ?? null,
        ]);

        $profile = UserProfile::updateOrCreate(['user_id' => $user->id], [
            'phone' => $attributes['phone'],
            'birth_date' => $attributes['birth_date'],
            'address' => $attributes['address'],
        ]);

        return CustomerUser::updateOrCreate(['user_id' => $user->id], [
            'accurate_id' => $attributes['accurate_id'],
            'customer_code' => $attributes['customer_code'],
            'user_profile_id' => $profile->id,
            'total_visits' => $attributes['total_visits'],
            'lifetime_spending' => $attributes['lifetime_spending'],
            'tier_id' => $attributes['tier_id'],
        ]);
    }

    private function seedStaff(): User
    {
        return User::updateOrCreate(['email' => 'api.staff@126club.test'], [
            'name' => 'API Demo Staff',
            'email_verified_at' => now(),
            'password' => Hash::make(self::CUSTOMER_PASSWORD),
            'type' => 'internal',
        ]);
    }

    private function seedPublicContent(Area $area): void
    {
        Event::updateOrCreate(['slug' => 'api-demo-tonight'], [
            'area_id' => $area->id,
            'name' => 'API Demo Tonight',
            'description' => 'Event aktif hari ini untuk mencoba filter today.',
            'start_date' => today(),
            'end_date' => today(),
            'start_time' => '20:00:00',
            'end_time' => '03:00:00',
            'is_active' => true,
            'price_adjustment_type' => 'percentage',
            'price_adjustment_value' => 10,
        ]);

        Event::updateOrCreate(['slug' => 'api-demo-weekend'], [
            'area_id' => $area->id,
            'name' => 'API Demo Weekend Party',
            'description' => 'Event mendatang untuk mencoba daftar event default.',
            'start_date' => today()->addDays(7),
            'end_date' => today()->addDays(8),
            'start_time' => '21:00:00',
            'end_time' => '04:00:00',
            'is_active' => true,
            'price_adjustment_type' => 'fixed',
            'price_adjustment_value' => 250_000,
        ]);

        Event::updateOrCreate(['slug' => 'api-demo-past'], [
            'area_id' => $area->id,
            'name' => 'API Demo Past Event',
            'description' => 'Event lampau untuk mencoba filter past.',
            'start_date' => today()->subDays(10),
            'end_date' => today()->subDays(9),
            'start_time' => '20:00:00',
            'end_time' => '02:00:00',
            'is_active' => true,
            'price_adjustment_type' => 'fixed',
            'price_adjustment_value' => 100_000,
        ]);

        Promo::updateOrCreate(['slug' => 'api-demo-happy-hour'], [
            'name' => 'API Demo Happy Hour',
            'description' => 'Promo aktif hari ini untuk pengujian mobile API.',
            'start_date' => today()->subDay(),
            'end_date' => today()->addDays(14),
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'is_active' => true,
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'terms_conditions' => 'Berlaku satu kali per transaksi demo.',
        ]);

        Promo::updateOrCreate(['slug' => 'api-demo-cashback'], [
            'name' => 'API Demo Cashback',
            'description' => 'Promo mendatang dengan potongan tetap.',
            'start_date' => today()->addDays(5),
            'end_date' => today()->addDays(30),
            'start_time' => '19:00:00',
            'end_time' => '23:59:00',
            'is_active' => true,
            'discount_type' => 'fixed',
            'discount_value' => 150_000,
            'terms_conditions' => 'Minimum transaksi Rp1.000.000.',
        ]);
    }

    private function seedPaymentInformation(): void
    {
        BankAccount::updateOrCreate(['account_number' => '12601260126'], [
            'bank_name' => 'BCA',
            'account_holder' => 'PT One Two Six Demo',
            'is_active' => true,
        ]);

        BankAccount::updateOrCreate(['account_number' => '1260000126'], [
            'bank_name' => 'Mandiri',
            'account_holder' => 'PT One Two Six Demo',
            'is_active' => true,
        ]);

        QrisSetting::updateOrCreate(['name' => '126 Club Demo QRIS'], [
            'image_path' => null,
            'is_active' => true,
        ]);

        WhatsappSetting::updateOrCreate(['phone_number' => '6281266660126'], [
            'description' => 'Nomor konfirmasi pembayaran demo API.',
            'is_active' => true,
        ]);
    }

    private function seedRewardsAndBottles(CustomerUser $customer): void
    {
        $cocktail = Reward::updateOrCreate(['name' => 'API Demo House Cocktail'], [
            'category' => 'drink',
            'description' => 'Satu house cocktail untuk pengujian redeem.',
            'points_required' => 500,
            'stock' => 25,
            'redeemed_count' => 1,
            'is_active' => true,
        ]);

        Reward::updateOrCreate(['name' => 'API Demo VIP Upgrade'], [
            'category' => 'vip',
            'description' => 'Upgrade meja VIP untuk data demo.',
            'points_required' => 1_500,
            'stock' => 5,
            'redeemed_count' => 0,
            'is_active' => true,
        ]);

        RewardRedemption::updateOrCreate([
            'reward_id' => $cocktail->id,
            'customer_user_id' => $customer->id,
            'notes' => 'API-DEMO-REDEMPTION',
        ], [
            'points_spent' => 500,
            'quantity' => 1,
            'status' => 'completed',
        ]);

        CustomerKeep::updateOrCreate([
            'customer_user_id' => $customer->id,
            'item_name' => 'API Demo Jack Daniels',
        ], [
            'type' => 'weekend_event',
            'quantity' => 0.75,
            'unit' => 'bottle',
            'notes' => 'Bottle keep aktif untuk endpoint /bottles.',
            'status' => 'active',
            'stored_at' => now()->subDays(2),
        ]);

        CustomerKeep::updateOrCreate([
            'customer_user_id' => $customer->id,
            'item_name' => 'API Demo Wine',
        ], [
            'type' => 'weekday',
            'quantity' => 0,
            'unit' => 'bottle',
            'notes' => 'Contoh bottle keep yang sudah habis.',
            'status' => 'used',
            'stored_at' => now()->subMonth(),
            'opened_at' => now()->subWeek(),
        ]);
    }

    /**
     * @param  array<string, Area|Tabel>  $areas
     */
    private function seedBookingHistory(CustomerUser $customer, User $staff, array $areas): void
    {
        $user = $customer->user;

        TableReservation::updateOrCreate(['booking_code' => 9_900_001], [
            'booking_name' => $user->name,
            'table_id' => $areas['available']->id,
            'customer_id' => $user->id,
            'reservation_date' => today()->addDays(3),
            'reservation_time' => '20:00:00',
            'status' => 'confirmed',
            'note' => 'Booking mendatang untuk pengujian cancel.',
            'down_payment_amount' => 250_000,
            'check_in_qr_code' => 'API-DEMO-FUTURE-QR',
            'check_in_qr_expires_at' => now()->addDays(4),
        ]);

        $activeBooking = TableReservation::updateOrCreate(['booking_code' => 9_900_002], [
            'booking_name' => $user->name,
            'table_id' => $areas['active']->id,
            'customer_id' => $user->id,
            'reservation_date' => today(),
            'reservation_time' => '19:00:00',
            'status' => 'checked_in',
            'note' => 'Booking aktif untuk request lagu dan display message.',
            'down_payment_amount' => 500_000,
            'check_in_qr_code' => 'API-DEMO-ACTIVE-QR',
            'check_in_qr_expires_at' => now()->addHours(8),
        ]);

        $activeSession = TableSession::updateOrCreate(['session_code' => 'API-DEMO-ACTIVE-SESSION'], [
            'table_reservation_id' => $activeBooking->id,
            'table_id' => $areas['active']->id,
            'customer_id' => $user->id,
            'waiter_id' => $staff->id,
            'pax' => 4,
            'check_in_qr_code' => 'API-DEMO-ACTIVE-SESSION-QR',
            'check_in_qr_expires_at' => now()->addHours(8),
            'checked_in_at' => now()->subHour(),
            'status' => 'active',
            'notes' => 'Sesi aktif khusus pengujian mobile API.',
        ]);

        $activeBilling = Billing::updateOrCreate(['table_session_id' => $activeSession->id], [
            'area_id' => $areas['lounge']->id,
            'is_walk_in' => false,
            'is_booking' => true,
            'minimum_charge' => 1_000_000,
            'orders_total' => 450_000,
            'subtotal' => 1_000_000,
            'tax' => 100_000,
            'tax_percentage' => 10,
            'service_charge' => 50_000,
            'service_charge_percentage' => 5,
            'discount_amount' => 0,
            'song_tip' => 25_000,
            'display_tip' => 15_000,
            'grand_total' => 1_190_000,
            'paid_amount' => 500_000,
            'remaining_balance' => 690_000,
            'is_debt' => true,
            'is_parsial_payment' => true,
            'billing_status' => 'partially_paid',
            'transaction_code' => 'API-DEMO-TRX-ACTIVE',
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'notes' => 'Billing aktif demo.',
        ]);

        $activeSession->update(['billing_id' => $activeBilling->id]);

        $this->seedOrder($activeSession, $customer, $staff, $areas['lounge']);
        $this->seedRequests($activeSession, $customer);
        $this->seedCompletedBooking($customer, $staff, $areas['vip'], $areas['vip_area']);
    }

    private function seedOrder(TableSession $session, CustomerUser $customer, User $staff, Area $area): void
    {
        $drink = InventoryItem::updateOrCreate(['code' => 'API-DEMO-DRINK'], [
            'accurate_id' => 9_901_001,
            'name' => 'API Demo Signature Drink',
            'pos_name' => 'Demo Drink',
            'category_type' => 'beverage',
            'item_type' => 'ITEM',
            'price' => 150_000,
            'stock_quantity' => 50,
            'threshold' => 5,
            'unit' => 'glass',
            'is_active' => true,
            'is_visible_in_pos' => true,
            'include_tax' => true,
            'include_service_charge' => true,
            'is_item_group' => false,
            'is_group_sold_out' => false,
            'item_produced' => true,
            'material_produced' => false,
        ]);

        $snack = InventoryItem::updateOrCreate(['code' => 'API-DEMO-SNACK'], [
            'accurate_id' => 9_901_002,
            'name' => 'API Demo French Fries',
            'pos_name' => 'Demo Fries',
            'category_type' => 'food',
            'item_type' => 'ITEM',
            'price' => 75_000,
            'stock_quantity' => 40,
            'threshold' => 5,
            'unit' => 'plate',
            'is_active' => true,
            'is_visible_in_pos' => true,
            'include_tax' => true,
            'include_service_charge' => true,
            'is_item_group' => false,
            'is_group_sold_out' => false,
            'item_produced' => true,
            'material_produced' => false,
        ]);

        $order = Order::updateOrCreate(['order_number' => 'API-DEMO-ORDER-001'], [
            'table_session_id' => $session->id,
            'customer_user_id' => $customer->id,
            'area_id' => $area->id,
            'created_by' => $staff->id,
            'status' => 'completed',
            'items_total' => 450_000,
            'discount_amount' => 0,
            'total' => 450_000,
            'ordered_at' => now()->subMinutes(50),
            'completed_at' => now()->subMinutes(20),
            'notes' => 'Order demo yang tampil pada histori booking.',
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order->id,
            'item_code' => $drink->code,
        ], [
            'inventory_item_id' => $drink->id,
            'item_name' => $drink->name,
            'quantity' => 2,
            'price' => 150_000,
            'subtotal' => 300_000,
            'discount_amount' => 0,
            'tax_amount' => 30_000,
            'service_charge_amount' => 15_000,
            'preparation_location' => 'bar',
            'status' => 'served',
            'served_at' => now()->subMinutes(30),
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order->id,
            'item_code' => $snack->code,
        ], [
            'inventory_item_id' => $snack->id,
            'item_name' => $snack->name,
            'quantity' => 2,
            'price' => 75_000,
            'subtotal' => 150_000,
            'discount_amount' => 0,
            'tax_amount' => 15_000,
            'service_charge_amount' => 7_500,
            'preparation_location' => 'kitchen',
            'status' => 'served',
            'served_at' => now()->subMinutes(25),
        ]);
    }

    private function seedRequests(TableSession $session, CustomerUser $customer): void
    {
        SongRequest::updateOrCreate([
            'customer_user_id' => $customer->id,
            'song_title' => 'API Demo Song',
        ], [
            'table_session_id' => $session->id,
            'artist' => 'Demo Artist',
            'cover_image' => null,
            'preview_url' => null,
            'tip' => 25_000,
            'status' => 'pending',
            'created_at' => now()->subMinutes(15),
        ]);

        DisplayMessageRequest::updateOrCreate([
            'customer_id' => $customer->user_id,
            'message' => 'Selamat datang di API Demo 126 Club!',
        ], [
            'table_session_id' => $session->id,
            'tip' => 15_000,
            'status' => 'pending',
            'created_at' => now()->subMinutes(10),
        ]);
    }

    private function seedCompletedBooking(CustomerUser $customer, User $staff, Tabel $table, Area $area): void
    {
        $booking = TableReservation::updateOrCreate(['booking_code' => 9_900_003], [
            'booking_name' => $customer->user->name,
            'table_id' => $table->id,
            'customer_id' => $customer->user_id,
            'reservation_date' => today()->subDays(7),
            'reservation_time' => '20:00:00',
            'status' => 'completed',
            'note' => 'Booking selesai untuk histori API.',
            'down_payment_amount' => 1_000_000,
        ]);

        $session = TableSession::updateOrCreate(['session_code' => 'API-DEMO-COMPLETED-SESSION'], [
            'table_reservation_id' => $booking->id,
            'table_id' => $table->id,
            'customer_id' => $customer->user_id,
            'waiter_id' => $staff->id,
            'pax' => 8,
            'checked_in_at' => now()->subDays(7)->setTime(20, 0),
            'checked_out_at' => now()->subDays(7)->setTime(23, 30),
            'status' => 'completed',
            'notes' => 'Sesi selesai untuk histori booking.',
        ]);

        $billing = Billing::updateOrCreate(['table_session_id' => $session->id], [
            'area_id' => $area->id,
            'is_walk_in' => false,
            'is_booking' => true,
            'minimum_charge' => 3_000_000,
            'orders_total' => 3_250_000,
            'subtotal' => 3_250_000,
            'tax' => 325_000,
            'tax_percentage' => 10,
            'service_charge' => 162_500,
            'service_charge_percentage' => 5,
            'discount_amount' => 250_000,
            'song_tip' => 0,
            'display_tip' => 0,
            'grand_total' => 3_487_500,
            'paid_amount' => 3_487_500,
            'remaining_balance' => 0,
            'is_debt' => false,
            'is_parsial_payment' => false,
            'billing_status' => 'paid',
            'paid_at' => now()->subDays(7)->setTime(23, 20),
            'transaction_code' => 'API-DEMO-TRX-COMPLETED',
            'payment_mode' => 'normal',
            'payment_method' => 'qris',
            'payment_reference_number' => 'API-DEMO-PAID-001',
            'notes' => 'Billing lunas demo.',
        ]);

        $session->update(['billing_id' => $billing->id]);
    }
}
