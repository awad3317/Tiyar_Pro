<?php

namespace Tests\Feature;

use App\Enums\ParcelStatus;
use App\Models\Office;
use App\Models\Parcel;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncomingParcelEditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
    }

    private function makeOffice(string $phone = '770100100'): Office
    {
        return Office::create([
            'name'                  => 'مكتب الاختبار',
            'branch_name'           => 'صنعاء',
            'whatsapp_sender_phone' => $phone,
            'httpsms_from_phone'    => '+967' . $phone,
            'httpsms_api_key'       => 'test-key',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);
    }

    public function test_office_can_edit_in_office_incoming_parcel_only(): void
    {
        $office = $this->makeOffice('770100101');
        $otherOffice = $this->makeOffice('770100102');
        $office->syncFeaturesStatus(['parcel_cod_amount', 'parcel_source_region', 'parcel_slot_number']);
        $office->refreshFeaturesCache();

        $inOfficeParcel = Parcel::create([
            'office_id'       => $office->id,
            'recipient_name'  => 'أحمد القديم',
            'recipient_phone' => '771111111',
            'package_type'    => 'كرتون صغير',
            'receipt_number'  => '10',
            'status'          => ParcelStatus::InOffice,
        ]);

        // 1. تعديل ناجح لطرد حالته "في المكتب" مع توحيد الأرقام المشرقية
        $this->actingAs($office, 'office')
            ->putJson(route('office.api.parcels.update', $inOfficeParcel), [
                'package_type'    => 'كرتون كبير معدّل',
                'receipt_number'  => '٢٥',
                'recipient_name'  => 'أحمد المعدّل',
                'recipient_phone' => '+967 ٧٧٢٢٢٢٢٢٢',
                'cod_amount'      => '4500',
                'source_region'   => 'المكلا',
                'slot_number'     => '١٢',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'parcel'  => [
                    'id'              => $inOfficeParcel->id,
                    'package_type'    => 'كرتون كبير معدّل',
                    'receipt_number'  => '25',
                    'recipient_name'  => 'أحمد المعدّل',
                    'recipient_phone' => '772222222',
                    'cod_amount'      => 4500,
                    'payment_type'    => 'postpaid',
                    'source_region'   => 'المكلا',
                    'slot_number'     => '12',
                ],
            ]);

        // 2. مكتب آخر لا يستطيع تعديل طرد ليس له (403)
        $this->actingAs($otherOffice, 'office')
            ->putJson(route('office.api.parcels.update', $inOfficeParcel), [
                'package_type'    => 'محاولة اختراق',
                'recipient_phone' => '773333333',
            ])
            ->assertStatus(403);

        // 3. طرد حالته "تم التسليم" لا يمكن تعديله (422)
        $deliveredParcel = Parcel::create([
            'office_id'       => $office->id,
            'recipient_name'  => 'مستلم منتهي',
            'recipient_phone' => '774444444',
            'package_type'    => 'كيس ملابس',
            'status'          => ParcelStatus::Delivered,
            'delivered_at'    => now(),
        ]);

        $this->actingAs($office, 'office')
            ->putJson(route('office.api.parcels.update', $deliveredParcel), [
                'package_type'    => 'تعديل مرفوض',
                'recipient_phone' => '774444444',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'لا يمكن تعديل الطرد إلا عندما تكون حالته «في المكتب» فقط.',
            ]);

        // 4. طرد حالته "مرتجع" لا يمكن تعديله (422)
        $returnedParcel = Parcel::create([
            'office_id'       => $office->id,
            'recipient_name'  => 'مستلم مرتجع',
            'recipient_phone' => '775555555',
            'package_type'    => 'ظرف',
            'status'          => ParcelStatus::Returned,
        ]);

        $this->actingAs($office, 'office')
            ->putJson(route('office.api.parcels.update', $returnedParcel), [
                'package_type'    => 'تعديل مرفوض',
                'recipient_phone' => '775555555',
            ])
            ->assertStatus(422);
    }
}
