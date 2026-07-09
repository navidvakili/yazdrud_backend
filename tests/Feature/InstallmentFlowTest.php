<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\TermCoupon;
use App\Models\VoucherInstallmentItem;
use App\Models\Registertut;
use App\Models\RegistrationInstallment;
use App\Models\RegistertutsPayment;
use App\Models\GatewayTransaction;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * تست یکپارچه‌سازی جریان تقسیط بن خرید
 *
 * این تست سناریوی کامل زیر را بررسی می‌کند:
 * ۱. ایجاد بن خرید با قابلیت تقسیط
 * ۲. اعتبارسنجی بن خرید در فرم ثبت‌نام (بازگشت اطلاعات اقساط)
 * ۳. ثبت‌نام کاربر با بن خرید تقسیطی
 * ۴. مشاهده اقساط در باشگاه فراگیران (Learner Club)
 *
 * کد فراگیر مورد استفاده: ۹۵۴۲۴۹۶
 */
class InstallmentFlowTest extends TestCase
{
    private const ENROLLMENT_CODE = '9542496';

    private Course $course;
    private TermCoupon $coupon;
    private Registertut $registration;

    /**
     * Create tables if they don't exist yet.
     * Called from setUp() after the app is bootstrapped.
     * These tables have no "create" migration files (they were created
     * directly in MySQL), so we create them here for the in-memory SQLite.
     */
    private function createTables(): void
    {
        if (Schema::hasTable('courses')) {
            return;
        }

        Schema::dropIfExists('registertuts_payments');
        Schema::dropIfExists('gateway_transactions');
        Schema::dropIfExists('registration_installments');
        Schema::dropIfExists('voucher_installment_items');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('registertuts');
        Schema::dropIfExists('term_coupons');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('amount')->default('0');
            $table->boolean('active')->default(true);
            $table->integer('capacity')->default(0);
            $table->integer('registered_count')->default(0);
            $table->string('duration')->nullable();
            $table->string('instructor')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->text('days_of_week')->nullable();
            $table->string('course_time')->nullable();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->text('sections')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->text('syllabus')->nullable();
            $table->string('registration_start_date')->nullable();
            $table->string('registration_end_date')->nullable();
            $table->text('prerequisites')->nullable();
            $table->unsignedBigInteger('instructor_id')->nullable();
            $table->timestamps();
        });

        Schema::create('term_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code', 50)->unique();
            $table->string('type')->default('discount');
            $table->string('type_discount')->default('money');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->integer('value')->default(0);
            $table->integer('capacity')->default(0);
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('start_date')->nullable();
            $table->string('finish_date')->nullable();
            $table->integer('max_discount')->nullable();
            $table->string('national_code', 20)->nullable();
            $table->boolean('enable_installment')->default(false);
            $table->integer('prepayment_amount')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });

        Schema::create('voucher_installment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('term_coupon_id');
            $table->string('title');
            $table->integer('amount');
            $table->string('due_date');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('registertuts', function (Blueprint $table) {
            $table->id();
            $table->string('kodmeli', 20);
            $table->unsignedBigInteger('course_id');
            $table->string('type', 10)->nullable();
            $table->string('fullname');
            $table->string('id_edu')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('payment_method', 20)->default('online');
            $table->string('bank_receipt')->nullable();
            $table->boolean('verified_receipt')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('rejected_receipt')->default(false);
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('note')->nullable();
            $table->boolean('certificate_approved')->default(false);
            $table->timestamp('certificate_approved_at')->nullable();
            $table->unsignedBigInteger('certificate_approved_by')->nullable();
            $table->text('skills')->nullable();
            $table->text('motivation')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('enrollment_code', 20)->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->integer('discount_amount')->nullable();
            $table->integer('prepayment_amount')->nullable();
            $table->boolean('refunded')->default(false);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('register_id');
            $table->string('certificate_number', 20)->unique();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('registration_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('register_id');
            $table->unsignedBigInteger('voucher_installment_item_id')->nullable();
            $table->string('title');
            $table->integer('amount');
            $table->string('due_date');
            $table->string('payment_method', 20)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->integer('paid_amount')->nullable();
            $table->string('tracking_number')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->nullable();
            $table->string('port', 50)->nullable();
            $table->string('username', 100)->nullable();
            $table->bigInteger('price')->default(0);
            $table->string('ref_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('card_number')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('ip', 50)->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('registertuts_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('register_id');
            $table->timestamps();
        });
    }

    /**
     * Create fresh fixture data before each test, wrapped in a transaction
     * so changes are rolled back automatically in tearDown().
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        DB::beginTransaction();

        // =====================================================
        // مرحله ۱: ایجاد داده‌های پیش‌نیاز
        // =====================================================

        // ایجاد یک دوره آموزشی با ظرفیت کافی
        $this->course = Course::create([
            'title'         => 'دوره جامع کارآفرینی',
            'amount'        => '5000000', // ۵,۰۰۰,۰۰۰ ریال
            'active'        => true,
            'capacity'      => 50,
            'registered_count' => 0,
            'duration'      => '۳۲ ساعت',
            'instructor'    => 'دکتر احمدی',
            'start_date'    => '1405/05/01',
            'end_date'      => '1405/06/15',
            'days_of_week'  => '["شنبه", "دوشنبه"]',
            'course_time'   => '۱۴-۱۶',
            'location'      => 'مرکز کارآفرینی دانشگاه علم و هنر',
        ]);

        // ایجاد بن خرید از نوع تخفیف نقدی با قابلیت تقسیط
        $this->coupon = TermCoupon::create([
            'title'              => 'بن تخفیف کارآفرینی',
            'code'               => 'ENT9542',
            'type'               => 'discount',
            'type_discount'      => 'money',
            'value'              => 3000000,
            'course_id'          => $this->course->id,
            'capacity'           => 10,
            'used_count'         => 0,
            'is_active'          => true,
            'start_date'         => '1405/01/01',
            'finish_date'        => '1405/12/30',
            'enable_installment' => true,
            'prepayment_amount'  => 0,
            'payment_method'     => 'online',
        ]);

        // ایجاد آیتم‌های قسط برای بن خرید (۳ قسط)
        VoucherInstallmentItem::create([
            'term_coupon_id' => $this->coupon->id,
            'title'          => 'قسط اول',
            'amount'         => 1000000,
            'due_date'       => '1405/07/15',
            'sort_order'     => 1,
        ]);

        VoucherInstallmentItem::create([
            'term_coupon_id' => $this->coupon->id,
            'title'          => 'قسط دوم',
            'amount'         => 1000000,
            'due_date'       => '1405/08/15',
            'sort_order'     => 2,
        ]);

        VoucherInstallmentItem::create([
            'term_coupon_id' => $this->coupon->id,
            'title'          => 'قسط سوم',
            'amount'         => 1000000,
            'due_date'       => '1405/09/15',
            'sort_order'     => 3,
        ]);

        // =====================================================
        // مرحله ۲: ایجاد ثبت‌نام با بن خرید تقسیطی
        // =====================================================

        $this->registration = Registertut::create([
            'kodmeli'           => '1234567890',
            'course_id'         => $this->course->id,
            'type'              => '1',
            'fullname'          => 'علی محمدی',
            'mobile'            => '09123456789',
            'email'             => 'ali@example.com',
            'payment_method'    => 'online',
            'status'            => 'paid',
            'enrollment_code'   => self::ENROLLMENT_CODE,
            'coupon_id'         => $this->coupon->id,
            'discount_amount'   => 3000000,
            'prepayment_amount' => 0,
        ]);

        // ایجاد تراکنش درگاه پرداخت (موفق)
        $gateway = GatewayTransaction::create([
            'type'          => 'tuts',
            'port'          => 'IRANKISH',
            'username'      => '1234567890',
            'price'         => 5000000,
            'ref_id'        => 'TEST_REF_' . time(),
            'tracking_code' => 'TRC_' . time(),
            'card_number'   => '123456******1234',
            'status'        => 'SUCCEED',
            'ip'            => '127.0.0.1',
            'payment_date'  => now(),
        ]);

        RegistertutsPayment::create([
            'transaction_id' => $gateway->id,
            'register_id'    => $this->registration->id,
        ]);

        // ایجاد اقساط ثبت‌نام بر اساس الگوی اقساط بن خرید
        $items = $this->coupon->installmentItems()->orderBy('sort_order')->get();
        foreach ($items as $item) {
            RegistrationInstallment::create([
                'register_id'                  => $this->registration->id,
                'voucher_installment_item_id'  => $item->id,
                'title'                        => $item->title,
                'amount'                       => $item->amount,
                'due_date'                     => $item->due_date,
                'payment_method'               => $this->coupon->payment_method,
                'status'                       => 'pending',
            ]);
        }

        // قسط اول را به‌عنوان پرداخت شده علامت بزن
        $firstInst = $this->registration->installments()->first();
        if ($firstInst) {
            $firstInst->update([
                'status'      => 'paid',
                'paid_at'     => now(),
                'paid_amount' => $firstInst->amount,
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_coupon_validation_returns_installment_data(): void
    {
        // Act: فراخوانی سرویس اعتبارسنجی بن خرید
        $response = $this->postJson('/api/coupons/validate', [
            'code'      => 'ENT9542',
            'course_id' => (string) $this->course->id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
            ]);

        $data = $response->json();

        // بررسی وجود فیلدهای تقسیط در پاسخ
        $this->assertArrayHasKey('coupon', $data);
        $this->assertTrue($data['coupon']['enable_installment']);
        $this->assertEquals('online', $data['coupon']['payment_method']);
        $this->assertCount(3, $data['coupon']['installment_items']);

        // بررسی جزئیات آیتم‌های قسط
        $items = $data['coupon']['installment_items'];
        $this->assertEquals('قسط اول', $items[0]['title']);
        $this->assertEquals(1000000, $items[0]['amount']);
        $this->assertEquals('1405/07/15', $items[0]['due_date']);

        $this->assertEquals('قسط سوم', $items[2]['title']);
        $this->assertEquals(1000000, $items[2]['amount']);
    }

    public function test_learner_club_returns_installment_data(): void
    {
        // Act: جستجوی فراگیر با کد فراگیر
        $response = $this->getJson('/api/public/learner-club/lookup?code=' . self::ENROLLMENT_CODE);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'اطلاعات فراگیر با موفقیت یافت شد.',
            ]);

        $data = $response->json('data');

        // بررسی اطلاعات فردی
        $this->assertEquals('علی محمدی', $data['learner']['fullName']);
        $this->assertEquals('1234567890', $data['learner']['nationalId']);

        // بررسی اطلاعات دوره
        $this->assertCount(1, $data['courses']);

        $course = $data['courses'][0];
        $this->assertEquals('دوره جامع کارآفرینی', $course['courseTitle']);
        $this->assertEquals(5000000, $course['coursePrice']);
        $this->assertEquals('paid', $course['status']);

        // === بررسی وجود اطلاعات تقسیط ===
        $this->assertTrue($course['enableInstallment']);
        $this->assertNotNull($course['installmentItems']);
        $this->assertCount(3, $course['installmentItems']);

        // بررسی وضعیت اقساط
        $installments = $course['installmentItems'];

        // قسط اول: پرداخت شده
        $this->assertEquals('قسط اول', $installments[0]['title']);
        $this->assertEquals(1000000, $installments[0]['amount']);
        $this->assertEquals('paid', $installments[0]['status']);
        $this->assertNotNull($installments[0]['paid_at']);

        // قسط دوم: در انتظار پرداخت
        $this->assertEquals('قسط دوم', $installments[1]['title']);
        $this->assertEquals(1000000, $installments[1]['amount']);
        $this->assertEquals('pending', $installments[1]['status']);
        $this->assertNull($installments[1]['paid_at']);

        // قسط سوم: در انتظار پرداخت
        $this->assertEquals('قسط سوم', $installments[2]['title']);
        $this->assertEquals('pending', $installments[2]['status']);

        // بررسی خلاصه اقساط
        $this->assertEquals('1 از 3 قسط پرداخت شده', $course['installmentSummary']);
    }

    public function test_learner_club_returns_installment_data_with_voucher_template_when_no_registration_installments(): void
    {
        // Arrange: ثبت‌نام جدید بدون اقساط ثبت‌نام (فقط بن دارای تقسیط است)
        $reg2 = Registertut::create([
            'kodmeli'           => '9876543210',
            'course_id'         => $this->course->id,
            'type'              => '2',
            'fullname'          => 'مریم رضایی',
            'mobile'            => '09123456788',
            'email'             => 'maryam@example.com',
            'payment_method'    => 'online',
            'status'            => 'paid',
            'enrollment_code'   => '9542497',
            'coupon_id'         => $this->coupon->id,
            'discount_amount'   => 3000000,
            'prepayment_amount' => 0,
        ]);

        // ایجاد تراکنش
        $gateway2 = GatewayTransaction::create([
            'type'          => 'tuts',
            'port'          => 'IRANKISH',
            'username'      => '9876543210',
            'price'         => 5000000,
            'ref_id'        => 'TEST_REF_2_' . time(),
            'tracking_code' => 'TRC_2_' . time(),
            'card_number'   => '123456******5678',
            'status'        => 'SUCCEED',
            'ip'            => '127.0.0.1',
            'payment_date'  => now(),
        ]);

        RegistertutsPayment::create([
            'transaction_id' => $gateway2->id,
            'register_id'    => $reg2->id,
        ]);

        // (بدون ایجاد RegistrationInstallment)

        // Act
        $response = $this->getJson('/api/public/learner-club/lookup?code=9542497');

        // Assert
        $response->assertStatus(200);
        $courseData = $response->json('data.courses.0');

        // بررسی داده‌های تقسیط از روی بن خرید (نه اقساط ثبت‌نام)
        $this->assertTrue($courseData['enableInstallment']);
        $this->assertNotNull($courseData['installmentItems']);
        $this->assertCount(3, $courseData['installmentItems']);

        // همه اقساط باید pending باشند چون هیچ کدام پرداخت نشده
        foreach ($courseData['installmentItems'] as $item) {
            $this->assertEquals('pending', $item['status']);
            $this->assertNull($item['paid_at']);
        }

        // خلاصه باید صفر از ۳ را نشان دهد
        $this->assertEquals('0 از 3 قسط پرداخت شده', $courseData['installmentSummary']);
    }

    public function test_coupon_without_installment_does_not_return_installment_fields(): void
    {
        // Arrange: بن خرید ساده (بدون تقسیط)
        $simpleCoupon = TermCoupon::create([
            'title'         => 'بن تخفیف ساده',
            'code'          => 'SIMPLE01',
            'type'          => 'discount',
            'type_discount' => 'percent',
            'value'         => 20,
            'course_id'     => $this->course->id,
            'capacity'      => 10,
            'used_count'    => 0,
            'is_active'     => true,
            'start_date'    => '1405/01/01',
            'finish_date'   => '1405/12/30',
        ]);

        // Act
        $response = $this->postJson('/api/coupons/validate', [
            'code'      => 'SIMPLE01',
            'course_id' => (string) $this->course->id,
        ]);

        // Assert
        $response->assertStatus(200);
        $couponData = $response->json('coupon');

        // بن ساده نباید فیلدهای تقسیط را داشته باشد
        $this->assertArrayNotHasKey('enable_installment', $couponData);
        $this->assertArrayNotHasKey('installment_items', $couponData);
    }

    public function test_coupon_validation_fails_for_invalid_code(): void
    {
        $response = $this->postJson('/api/coupons/validate', [
            'code'      => 'INVALID99',
            'course_id' => (string) $this->course->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => false,
            ]);
    }
}
