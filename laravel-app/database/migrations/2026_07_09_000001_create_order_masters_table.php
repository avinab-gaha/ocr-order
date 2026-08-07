<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_masters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_code', 50)->nullable();
            $table->date('quotation_date')->nullable();
            $table->bigInteger('quotation_number')->nullable();
            $table->integer('quotation_branch_number')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('status_update_date')->nullable();
            $table->string('handling_office_code', 50)->nullable();
            $table->string('handling_office', 255)->nullable();
            $table->string('handling_staff_code', 50)->nullable();
            $table->string('handling_staff_name', 255)->nullable();
            $table->string('customer_code', 50)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->string('billing_code', 50)->nullable();
            $table->string('billing_name', 255)->nullable();
            $table->char('customer_zip_code', 8)->nullable();
            $table->string('customer_prefecture', 20)->nullable();
            $table->string('customer_address1', 255)->nullable();
            $table->string('customer_address2', 255)->nullable();
            $table->string('customer_address3', 255)->nullable();

            $table->string('payment_terms', 100)->nullable();
            $table->string('billing_closing_date', 20)->nullable();
            $table->date('scheduled_payment')->nullable();
            $table->string('tax_calculation_class', 50)->nullable();

            $table->string('contract_office_code', 50)->nullable();
            $table->string('contract_office', 255)->nullable();
            $table->string('staff_code', 50)->nullable();
            $table->string('staff_name', 255)->nullable();
            $table->string('payment_destination_code', 50)->nullable();
            $table->string('payment_destination_name', 255)->nullable();

            $table->char('staff_zip_code', 8)->nullable();
            $table->string('staff_prefecture', 20)->nullable();
            $table->string('staff_address1', 255)->nullable();
            $table->string('staff_address2', 255)->nullable();
            $table->string('staff_address3', 255)->nullable();

            $table->string('payment_conditions', 100)->nullable();
            $table->string('payment_closing_date', 20)->nullable();
            $table->date('scheduled_payment_date')->nullable();
            $table->string('staff_tax_classification', 50)->nullable();

            $table->date('planned_service_date')->nullable();
            $table->time('planned_service_time')->nullable();
            $table->integer('planned_users_count')->nullable();
            $table->text('user_information')->nullable();

            $table->string('service_area_class', 50)->nullable();
            $table->string('service_location', 255)->nullable();
            $table->integer('desired_staff_count')->nullable();
            $table->string('service_classification', 10)->nullable();

            $table->text('required_qualifications')->nullable();
            $table->string('subject', 255)->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('expiration_date')->nullable();
            // $table->timestamp('created_at')->nullable();
            $table->string('registered_by', 255)->nullable();
            // $table->timestamp('updated_at')->nullable();
            $table->string('last_updated_by', 255)->nullable();
            $table->text('request_status')->nullable();
            $table->timestamp('request_date_time')->nullable();
            $table->text('request_approved_by')->nullable();

            $table->text('shift_status')->nullable();
            $table->timestamp('shift_date_time')->nullable();
            $table->text('shift_approved_by')->nullable();
            
            $table->text('order_confirmed_Status')->nullable();
            $table->timestamp('order_confirmed_datetime')->nullable();
            $table->text('order_confirmed_by')->nullable();

            $table->text('sales_confirmed_status')->nullable();
            $table->timestamp('sales_confirmed_datetime')->nullable();
            $table->text('sales_confirmed_by')->nullable();

            $table->text('bill_closed_status')->nullable();
            $table->timestamp('bill_closed_datetime')->nullable();
            $table->text('bill_closed_confirmed_by')->nullable();

            $table->text('payment_status')->nullable();
            $table->timestamp('payment_datetime')->nullable();
            $table->text('payment_confirmed_by')->nullable();
            $table->string('quotation_output_class', 50)->nullable();
             $table->boolean('order_flg')->default(false);
              $table->string('service_type')->nullable();
             $table->integer('staff_id')->nullable();
             $table->time('required_start')->nullable();
            $table->time('required_end')->nullable();

            $table->decimal('total_quantity', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_base_cost', 15, 2)->default(0);
            $table->decimal('total_gross_profit', 15, 2)->default(0);
            $table->decimal('total_gross_profit_rate', 15, 2)->default(0);
            $table->decimal('total_consumption_tax', 15, 2)->default(0);

             $table->time('extension_time_from')->nullable();
            $table->time('extension_time_to')->nullable();

            $table->text('billing_information')->nullable();
            $table->text('accepted_case')->nullable();
            $table->text('tickets')->nullable();

            $table->text("report_remarks")->nullable();
            $table->integer('customer_id')->nullable();
             $table->text('staff_information')->nullable(); 
            $table->bigInteger('advance_payment')->nullable(); 
            $table->bigInteger('transportation_cost')->nullable();
            $table->string('bill_transaction_code', 50)->nullable();
            $table->string('payment_transaction_code', 50)->nullable();
            // Uploaded source file
            $table->string('source_file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('branch_number')->nullable();

             $table->time('extended_start_time')->nullable();
            $table->time('extended_end_time')->nullable();
            $table->time('enter_start_time')->nullable();
            $table->time('enter_end_time')->nullable();
            $table->time('reference_time')->nullable();
            $table->time('extended_time_input')->nullable();
            $table->time('night_time_input')->nullable();
            $table->time('late_night_input')->nullable();
            $table->string('day_of_week_proc')->nullable();
            $table->boolean('same_day_request')->default(false);
            $table->boolean('extension')->default(false);
            $table->string('english_support')->nullable();
            $table->time('outdoor1_start_time')->nullable();
            $table->time('outdoor1_end_time')->nullable();
            $table->time('outdoor2_start_time')->nullable();
            $table->time('outdoor2_end_time')->nullable();
            $table->string('base_cost')->nullable();
             $table->text('staff_memo')->nullable();

            $table->string('sales_method', 100)->nullable();
            $table->date('credit_base_date')->nullable();
            $table->string('collection_type', 100)->nullable();

            $table->boolean('is_sameday_request')->default(0);
            $table->boolean('is_sameday_cancellation')->default(0);

            $table->boolean('is_pickup_up_drop')->default(0);
            $table->boolean('is_pickup_drop_preschooler')->default(0);
            $table->boolean('is_bathing')->default(0);

            $table->boolean('is_english_a')->default(0);
            $table->boolean('is_english_b')->default(0);
            $table->boolean('is_outdoor')->default(0);
               $table->time('extra_basic_hour')->nullable();
            $table->time('basic_time')->nullable();

            $table->time('housework_1_start_time')->nullable();
            $table->time('housework_2_start_time')->nullable();
            $table->time('housework_1_completion_time')->nullable();
            $table->time('housework_2_end_time')->nullable();
            $table->string('number_of_transfers')->nullable();
            $table->string('number_of_baths')->nullable();
            $table->string('number_of_pick_ups')->nullable();
             $table->time('house_work_time')->nullable();
            $table->time('outdoor_time')->nullable();
            // Audit / debugging trail
            $table->longText('raw_ocr_text')->nullable();
            $table->json('llm_raw_response')->nullable();
            $table->string('llm_provider')->nullable();

            // pending = auto-created preview awaiting human confirmation
            // confirmed = reviewed and accepted
            // failed = OCR/LLM mapping failed
            // flagged = some fields have low confidence, needs human review
            // $table->enum('status', ['pending', 'confirmed', 'failed', 'flagged'])->default('pending')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_masters');
    }
};
