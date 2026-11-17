<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Markup and variable schema for the Aseman Haftom travel documents.
 */
final class TravelDocumentCatalog
{
    public static function html(string $filename): string
    {
        $path = resource_path('templates/'.$filename);

        if (! is_file($path)) {
            throw new \RuntimeException('فایل قالب پیدا نشد: '.$filename);
        }

        return (string) file_get_contents($path);
    }

    public static function footer(): string
    {
        return self::html('partials/document-footer.html');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function accommodationVariables(): array
    {
        return [
            ['name' => 'accommodation_type', 'label' => 'نوع اقامت', 'type' => 'string', 'is_required' => false, 'default_value' => 'hotel', 'description' => 'hotel | residence | villa یا کد اسنپ: Hotel, Pansion, Apartments, Guesthouse, Motel, Inn, Traditionalhouse', 'validation_rules' => 'max:40', 'sort_order' => 1],
            ['name' => 'accommodation_type_label', 'label' => 'برچسب نوع اقامت', 'type' => 'string', 'is_required' => false, 'description' => 'اگر پر شود عنوان واچر عیناً از این متن ساخته می‌شود (مثلاً هتل، اقامتگاه، ویلا).', 'validation_rules' => 'max:40', 'sort_order' => 2],
            ['name' => 'stay', 'label' => 'حالت جستجو', 'type' => 'string', 'is_required' => false, 'description' => 'external_metadata.stay در رزرو. مقدار villa بدون accommodation_type یعنی اقامتگاه.', 'validation_rules' => 'max:40', 'sort_order' => 3],
            ['name' => 'guest_name', 'label' => 'نام مهمان', 'type' => 'string', 'is_required' => true, 'description' => 'سرگروه. معادل guests[0] یا nameLeader + familyLeader یا invoice.owner.', 'validation_rules' => 'max:160', 'sort_order' => 4],
            ['name' => 'hotel_name', 'label' => 'نام محل اقامت', 'type' => 'string', 'is_required' => true, 'description' => 'hotel_hub_bookings.hotel_name یا reservations.finalHotelName / hotelName. برای ویلا و اقامتگاه هم همین فیلد.', 'validation_rules' => 'max:180', 'sort_order' => 5],
            ['name' => 'property_name', 'label' => 'نام نمایشی محل', 'type' => 'string', 'is_required' => false, 'description' => 'اگر باشد به‌جای hotel_name در عنوان نشان داده می‌شود.', 'validation_rules' => 'max:180', 'sort_order' => 6],
            ['name' => 'booking_reference', 'label' => 'کد رزرو', 'type' => 'string', 'is_required' => true, 'description' => 'reservation_code یا hub_public_id یا re_code. همان فیلد قالب قبلی hotel-voucher.', 'validation_rules' => 'max:80', 'sort_order' => 7],
            ['name' => 'voucher_number', 'label' => 'شماره واچر', 'type' => 'string', 'is_required' => false, 'description' => 'اگر خالی باشد booking_reference چاپ و در QR استفاده می‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 8],
            ['name' => 'status', 'label' => 'وضعیت', 'type' => 'string', 'is_required' => false, 'description' => 'confirmed | pending_payment | payment_on | cancelled | payed. hotel_hub_bookings.status', 'validation_rules' => 'max:40', 'sort_order' => 9],
            ['name' => 'status_label', 'label' => 'برچسب وضعیت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:60', 'sort_order' => 10],
            ['name' => 'check_in', 'label' => 'تاریخ ورود (میلادی)', 'type' => 'date', 'is_required' => true, 'description' => 'hotel_hub_bookings.checkin یا reservations.dateStartTour به صورت Y-m-d. برای نمایش شمسی از check_in_label استفاده کنید.', 'sort_order' => 11],
            ['name' => 'check_out', 'label' => 'تاریخ خروج (میلادی)', 'type' => 'date', 'is_required' => true, 'description' => 'checkout یا dateEndTour به صورت Y-m-d.', 'sort_order' => 12],
            ['name' => 'check_in_label', 'label' => 'تاریخ ورود شمسی', 'type' => 'string', 'is_required' => false, 'description' => 'متن آماده جلالی از سمت سایت (dateToJalali). اگر باشد تبدیل مجدد انجام نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 13],
            ['name' => 'check_out_label', 'label' => 'تاریخ خروج شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 14],
            ['name' => 'check_in_time', 'label' => 'ساعت ورود', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:20', 'sort_order' => 15],
            ['name' => 'check_out_time', 'label' => 'ساعت خروج', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:20', 'sort_order' => 16],
            ['name' => 'nights', 'label' => 'تعداد شب', 'type' => 'integer', 'is_required' => false, 'description' => 'hotel_hub_bookings.nights', 'min_value' => 0, 'sort_order' => 17],
            ['name' => 'room_count', 'label' => 'تعداد واحد', 'type' => 'integer', 'is_required' => true, 'default_value' => '1', 'min_value' => 1, 'max_value' => 50, 'sort_order' => 18],
            ['name' => 'board', 'label' => 'پذیرایی', 'type' => 'string', 'is_required' => false, 'description' => 'وعده غذا در سطح رزرو؛ اگر اتاق board/meal داشته باشد همان مقدم است.', 'validation_rules' => 'max:80', 'sort_order' => 19],
            ['name' => 'city', 'label' => 'شهر', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 20],
            ['name' => 'address', 'label' => 'آدرس', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:300', 'sort_order' => 21],
            ['name' => 'stars', 'label' => 'ستاره', 'type' => 'integer', 'is_required' => false, 'min_value' => 0, 'max_value' => 7, 'sort_order' => 22],
            ['name' => 'property_phone', 'label' => 'تلفن محل اقامت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:40', 'sort_order' => 23],
            ['name' => 'property', 'label' => 'مشخصات محل', 'type' => 'object', 'is_required' => false, 'description' => '{name, city, address, phone, stars}', 'sort_order' => 24],
            ['name' => 'rooms', 'label' => 'اتاق‌ها', 'type' => 'array', 'is_required' => false, 'description' => 'name|room_name|title, board|meal|meal_plan, adults, children, extra_beds, quantity. confirmation_code پذیرفته می‌شود ولی چاپ نمی‌شود.', 'sort_order' => 25],
            ['name' => 'guests', 'label' => 'مهمان‌ها', 'type' => 'array', 'is_required' => false, 'description' => 'guests[]: name یا first_name+last_name (firstName/lastName هم پذیرفته است)، national_id|nationalId|code_meli، passport|passportNumber، phone|mobile|cellPhone، type|age_type، is_leader', 'sort_order' => 26],
            ['name' => 'total_price', 'label' => 'مبلغ کل', 'type' => 'decimal', 'is_required' => true, 'description' => 'مبلغ نمایشی. سرویس فعلی سایت booking.price را با واحد تومان می‌فرستد. تبدیل ریال/تومان داخل قالب انجام نمی‌شود.', 'min_value' => 0, 'sort_order' => 27],
            ['name' => 'original_price', 'label' => 'مبلغ اولیه', 'type' => 'decimal', 'is_required' => false, 'description' => 'original_sell_price', 'min_value' => 0, 'sort_order' => 28],
            ['name' => 'discount', 'label' => 'تخفیف', 'type' => 'decimal', 'is_required' => false, 'min_value' => 0, 'sort_order' => 29],
            ['name' => 'currency', 'label' => 'واحد پول', 'type' => 'string', 'is_required' => true, 'default_value' => 'تومان', 'validation_rules' => 'max:20', 'sort_order' => 30],
            ['name' => 'payment', 'label' => 'پرداخت', 'type' => 'object', 'is_required' => false, 'description' => '{wallet_paid, gateway_amount, currency, transaction_id, transaction_type, status, paid_at_label}. مبالغ باید هم‌واحد currency باشند. invoice.amount در سایت ریال است؛ قبل از ارسال به واحد نمایش تبدیل شود.', 'sort_order' => 31],
            ['name' => 'invoice_number', 'label' => 'شماره فاکتور', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:40', 'sort_order' => 32],
            ['name' => 're_code', 'label' => 'کد رزرو سایت', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.re_code', 'validation_rules' => 'max:40', 'sort_order' => 33],
            ['name' => 'reservation_code', 'label' => 'کد تأیید تأمین‌کننده', 'type' => 'string', 'is_required' => false, 'description' => 'پذیرفته می‌شود ولی روی واچر چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 34],
            ['name' => 'confirmation_code', 'label' => 'کد تأیید تأمین‌کننده', 'type' => 'string', 'is_required' => false, 'description' => 'پذیرفته می‌شود ولی روی واچر چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 35],
            ['name' => 'supplier_code', 'label' => 'کد تأمین‌کننده', 'type' => 'string', 'is_required' => false, 'description' => 'پذیرفته می‌شود ولی روی واچر چاپ نمی‌شود.', 'validation_rules' => 'max:40', 'sort_order' => 36],
            ['name' => 'hub_public_id', 'label' => 'شناسه هاب', 'type' => 'string', 'is_required' => false, 'description' => 'شناسه سمت تأمین‌کننده. پذیرفته می‌شود ولی چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 37],
            ['name' => 'contact', 'label' => 'مخاطب', 'type' => 'object', 'is_required' => false, 'description' => '{name, phone, email}', 'sort_order' => 38],
            ['name' => 'contact_name', 'label' => 'نام تماس', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:160', 'sort_order' => 39],
            ['name' => 'contact_phone', 'label' => 'تلفن تماس', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.cell یا guest.phone', 'validation_rules' => 'max:40', 'sort_order' => 40],
            ['name' => 'contact_email', 'label' => 'ایمیل تماس', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:120', 'sort_order' => 41],
            ['name' => 'notes', 'label' => 'توضیحات', 'type' => 'text', 'is_required' => false, 'description' => 'hotel_hub_bookings.note', 'validation_rules' => 'max:2000', 'sort_order' => 42],
            ['name' => 'rules', 'label' => 'قوانین', 'type' => 'array', 'is_required' => false, 'description' => 'رشته یا {label, value|text|description}', 'sort_order' => 43],
            ['name' => 'agency_name', 'label' => 'نام آژانس', 'type' => 'string', 'is_required' => false, 'default_value' => 'آژانس مسافرتی آسمان هفتم', 'validation_rules' => 'max:120', 'sort_order' => 44],
            ['name' => 'agency_phone', 'label' => 'تلفن آژانس', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:40', 'sort_order' => 45],
            ['name' => 'agency_address', 'label' => 'آدرس آژانس', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:200', 'sort_order' => 46],
            ['name' => 'issue_date', 'label' => 'تاریخ صدور (میلادی)', 'type' => 'date', 'is_required' => false, 'sort_order' => 47],
            ['name' => 'issue_date_label', 'label' => 'تاریخ صدور شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 48],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function flightVariables(): array
    {
        return [
            ['name' => 'pnr', 'label' => 'کد رزرو PNR', 'type' => 'string', 'is_required' => true, 'description' => 'کد قابل اسکن روی بلیط. اگر PNR ایرلاین نیست، tracking_code یا re_code را اینجا بفرستید، نه شناسه هاب.', 'validation_rules' => 'max:80', 'sort_order' => 1],
            ['name' => 'ticket_number', 'label' => 'شماره بلیط', 'type' => 'string', 'is_required' => false, 'description' => 'شماره بلیط سطح رزرو. شماره هر مسافر روی passenger.ticket_number است.', 'validation_rules' => 'max:80', 'sort_order' => 2],
            ['name' => 'hub_public_id', 'label' => 'شناسه هاب', 'type' => 'string', 'is_required' => false, 'description' => 'flight_bookings.hub_public_id. پذیرفته می‌شود ولی روی بلیط چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 3],
            ['name' => 'tracking_code', 'label' => 'کد پیگیری', 'type' => 'string', 'is_required' => false, 'description' => 'flight_bookings.tracking_code', 'validation_rules' => 'max:80', 'sort_order' => 4],
            ['name' => 're_code', 'label' => 'کد رزرو سایت', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.re_code', 'validation_rules' => 'max:40', 'sort_order' => 5],
            ['name' => 'supplier_code', 'label' => 'کد تأمین‌کننده', 'type' => 'string', 'is_required' => false, 'description' => 'پذیرفته می‌شود ولی روی بلیط چاپ نمی‌شود.', 'validation_rules' => 'max:40', 'sort_order' => 6],
            ['name' => 'status', 'label' => 'وضعیت', 'type' => 'string', 'is_required' => false, 'description' => 'issued | booked | pending_payment | failed | cancel_pending', 'validation_rules' => 'max:40', 'sort_order' => 7],
            ['name' => 'status_label', 'label' => 'برچسب وضعیت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:60', 'sort_order' => 8],
            ['name' => 'trip_type', 'label' => 'نوع سفر', 'type' => 'string', 'is_required' => false, 'description' => 'one_way | round_trip | return. معادل route_snapshot.criteria.tripType', 'validation_rules' => 'max:20', 'sort_order' => 9],
            ['name' => 'summary', 'label' => 'خلاصه مسیر', 'type' => 'string', 'is_required' => false, 'description' => 'route_snapshot.summary', 'validation_rules' => 'max:180', 'sort_order' => 10],
            ['name' => 'airline', 'label' => 'ایرلاین', 'type' => 'string', 'is_required' => false, 'description' => 'پیش‌فرض اگر سگمنت airline نداشته باشد. route_snapshot.card.airlineLabel', 'validation_rules' => 'max:80', 'sort_order' => 11],
            ['name' => 'cabin_class', 'label' => 'کلاس کابین', 'type' => 'string', 'is_required' => false, 'description' => 'route_snapshot.card.cabinClassLabel', 'validation_rules' => 'max:40', 'sort_order' => 12],
            ['name' => 'fare_type', 'label' => 'نوع نرخ', 'type' => 'string', 'is_required' => false, 'description' => 'سیستمی/چارتر. route_snapshot.card.fareTypeLabel', 'validation_rules' => 'max:40', 'sort_order' => 13],
            ['name' => 'baggage', 'label' => 'بار مجاز', 'type' => 'string', 'is_required' => false, 'description' => 'پیش‌فرض سگمنت. route_snapshot.card.baggage', 'validation_rules' => 'max:80', 'sort_order' => 14],
            ['name' => 'passengers', 'label' => 'مسافران', 'type' => 'array', 'is_required' => true, 'description' => 'flight_bookings.passengers[]: name یا first_name/firstName + last_name/lastName، national_id/nationalId/code_meli، passport/passportNumber، type یا passengerType (ADT/CHD/INF)، type_label، ticket_number/ticketNumber', 'sort_order' => 15],
            ['name' => 'segments', 'label' => 'سگمنت‌ها', 'type' => 'array', 'is_required' => false, 'description' => 'اولویت اول مسیر. هر آیتم نام ستون reservation_flight_segments (direction, position, from_city_name, to_city_name, depart_date, depart_time, airline, flight_number) را می‌پذیرد و نام‌های FlightItineraryPresenter را هم: flightNumber, origin, destination, originLabel, originCityLabel, destinationLabel, destinationCityLabel, departureTime, arrivalTime, departureDateLabel, arrivalDateLabel, departureDateTime, arrivalDateTime, airlineLabel, marketingAirlineCode, cabinClassLabel, resBookDesigCode, baggage, durationLabel, equipment, optionIndex. فیلدهای اضافه: from_code, to_code, terminal, arrival_terminal, booking_class, cabin_class, duration.', 'sort_order' => 16],
            ['name' => 'reservation_flight_segments', 'label' => 'سگمنت‌های رزرو', 'type' => 'array', 'is_required' => false, 'description' => 'اگر segments خالی باشد، ردیف‌های جدول reservation_flight_segments با همان نام ستون‌ها چاپ می‌شوند.', 'sort_order' => 17],
            ['name' => 'route_snapshot', 'label' => 'اسنپ‌شات مسیر', 'type' => 'object', 'is_required' => false, 'description' => 'flight_bookings.route_snapshot: {summary, criteria:{tripType,origin,destination}, card:{legs[], airlineLabel, cabinClassLabel, fareTypeLabel, baggage, sellTotal, priceBreakdown[]}}', 'sort_order' => 18],
            ['name' => 'fare_breakdown', 'label' => 'ریز نرخ', 'type' => 'array', 'is_required' => false, 'description' => '{label, passenger_type|passengerType, quantity, base_fare, tax, total|totalFare}', 'sort_order' => 19],
            ['name' => 'price_breakdown', 'label' => 'ریز نرخ کارت', 'type' => 'array', 'is_required' => false, 'description' => 'اگر fare_breakdown نباشد استفاده می‌شود. معادل card.priceBreakdown: {label, passengerType, quantity, totalFare}', 'sort_order' => 20],
            ['name' => 'base_fare', 'label' => 'قیمت پایه', 'type' => 'decimal', 'is_required' => false, 'min_value' => 0, 'sort_order' => 21],
            ['name' => 'tax', 'label' => 'مالیات', 'type' => 'decimal', 'is_required' => false, 'min_value' => 0, 'sort_order' => 22],
            ['name' => 'discount', 'label' => 'تخفیف', 'type' => 'decimal', 'is_required' => false, 'min_value' => 0, 'sort_order' => 23],
            ['name' => 'total_price', 'label' => 'مبلغ کل', 'type' => 'decimal', 'is_required' => false, 'description' => 'مبلغ نمایشی. اگر خالی باشد sell_price و بعد route_snapshot.card.sellTotal.', 'min_value' => 0, 'sort_order' => 24],
            ['name' => 'sell_price', 'label' => 'قیمت فروش', 'type' => 'decimal', 'is_required' => false, 'description' => 'flight_bookings.sell_price (تومان در سایت).', 'min_value' => 0, 'sort_order' => 25],
            ['name' => 'currency', 'label' => 'واحد پول', 'type' => 'string', 'is_required' => false, 'default_value' => 'تومان', 'validation_rules' => 'max:20', 'sort_order' => 26],
            ['name' => 'payment', 'label' => 'پرداخت', 'type' => 'object', 'is_required' => false, 'description' => '{wallet_paid, gateway_amount, currency, transaction_id, paid_at_label}', 'sort_order' => 27],
            ['name' => 'invoice_number', 'label' => 'شماره فاکتور', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:40', 'sort_order' => 28],
            ['name' => 'contact_name', 'label' => 'نام تماس', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:160', 'sort_order' => 29],
            ['name' => 'contact_phone', 'label' => 'تلفن تماس', 'type' => 'string', 'is_required' => false, 'description' => 'flight_bookings.contact_phone', 'validation_rules' => 'max:40', 'sort_order' => 30],
            ['name' => 'contact_email', 'label' => 'ایمیل تماس', 'type' => 'string', 'is_required' => false, 'description' => 'flight_bookings.contact_email', 'validation_rules' => 'max:120', 'sort_order' => 31],
            ['name' => 'issued_at', 'label' => 'تاریخ صدور (میلادی)', 'type' => 'date', 'is_required' => false, 'description' => 'flight_bookings.issued_at به صورت Y-m-d. متن شمسی را در issued_at_label بفرستید.', 'sort_order' => 32],
            ['name' => 'issued_at_label', 'label' => 'تاریخ صدور شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 33],
            ['name' => 'rules', 'label' => 'قوانین', 'type' => 'array', 'is_required' => false, 'description' => 'رشته یا {label, value}', 'sort_order' => 34],
            ['name' => 'cancellation_policies', 'label' => 'سیاست کنسلی', 'type' => 'array', 'is_required' => false, 'description' => 'card.cancellationPolicies[]: {description, adultPenaltyPercentage, childPenaltyPercentage, infantPenaltyPercentage}', 'sort_order' => 35],
            ['name' => 'notes', 'label' => 'توضیحات', 'type' => 'text', 'is_required' => false, 'validation_rules' => 'max:2000', 'sort_order' => 36],
            ['name' => 'agency_name', 'label' => 'نام آژانس', 'type' => 'string', 'is_required' => false, 'default_value' => 'آژانس مسافرتی آسمان هفتم', 'validation_rules' => 'max:120', 'sort_order' => 37],
            ['name' => 'airline_pnr', 'label' => 'PNR ایرلاین', 'type' => 'string', 'is_required' => false, 'description' => 'PNR ایرلاین در صورت وجود؛ وگرنه pnr چاپ می‌شود.', 'validation_rules' => 'max:40', 'sort_order' => 38],
            ['name' => 'issued_time', 'label' => 'ساعت صدور', 'type' => 'string', 'is_required' => false, 'description' => 'H:i به وقت تهران', 'validation_rules' => 'max:10', 'sort_order' => 39],
            ['name' => 'baggage_rules', 'label' => 'قوانین بار', 'type' => 'array', 'is_required' => false, 'description' => 'رشته یا {label, value}. در کادر جداگانهٔ «قوانین بار» به‌صورت فهرست شماره‌دار.', 'sort_order' => 40],
            ['name' => 'cancellation_rules', 'label' => 'قوانین کنسلی', 'type' => 'array', 'is_required' => false, 'description' => 'رشته یا {label, value}. اگر cancellation_policies باشد، جدول آن نمایش داده می‌شود.', 'sort_order' => 41],
            ['name' => 'general_rules', 'label' => 'قوانین و مقررات', 'type' => 'array', 'is_required' => false, 'description' => 'رشته یا {label, value}. اولویت بر rules.', 'sort_order' => 42],
            ['name' => 'flight_notes', 'label' => 'نکات پرواز', 'type' => 'array', 'is_required' => false, 'description' => 'رشته‌ها. اگر خالی باشد نکات پیش‌فرض چاپ می‌شود.', 'sort_order' => 43],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function tourVoucherVariables(): array
    {
        return array_merge(self::reservationCoreVariables(), [
            ['name' => 'tour_kind', 'label' => 'نوع تور', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.type: abs_dom | abs_int | abs_oneDay. مقدار قدیمی oneDay هم تور یک‌روزه است.', 'validation_rules' => 'max:40', 'sort_order' => 20],
            ['name' => 'tour_name', 'label' => 'نام تور', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.tour_name. اگر subject خالی باشد عنوان از این فیلد است.', 'validation_rules' => 'max:180', 'sort_order' => 21],
            ['name' => 'origin', 'label' => 'مبدأ', 'type' => 'string', 'is_required' => false, 'description' => 'city_name(initialPoint_id) یا initialPoint.', 'validation_rules' => 'max:80', 'sort_order' => 22],
            ['name' => 'destination', 'label' => 'مقصد', 'type' => 'string', 'is_required' => false, 'description' => 'city_name(cityCode) یا city.', 'validation_rules' => 'max:80', 'sort_order' => 23],
            ['name' => 'date_start', 'label' => 'تاریخ حرکت (میلادی)', 'type' => 'date', 'is_required' => false, 'description' => 'dateStartTour به صورت Y-m-d.', 'sort_order' => 24],
            ['name' => 'date_end', 'label' => 'تاریخ بازگشت (میلادی)', 'type' => 'date', 'is_required' => false, 'description' => 'dateEndTour به صورت Y-m-d.', 'sort_order' => 25],
            ['name' => 'date_start_label', 'label' => 'تاریخ حرکت شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 26],
            ['name' => 'date_end_label', 'label' => 'تاریخ بازگشت شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 27],
            ['name' => 'duration', 'label' => 'مدت (شب)', 'type' => 'integer', 'is_required' => false, 'description' => 'reservations.duration', 'min_value' => 0, 'sort_order' => 28],
            ['name' => 'transportation', 'label' => 'کد حمل‌ونقل', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.transportation (air و مشابه).', 'validation_rules' => 'max:40', 'sort_order' => 29],
            ['name' => 'transportation_label', 'label' => 'حمل‌ونقل', 'type' => 'string', 'is_required' => false, 'description' => 'خروجی transportation_fa().', 'validation_rules' => 'max:40', 'sort_order' => 30],
            ['name' => 'company_departure', 'label' => 'شرکت رفت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 31],
            ['name' => 'time_departure', 'label' => 'ساعت رفت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:20', 'sort_order' => 32],
            ['name' => 'company_back', 'label' => 'شرکت برگشت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 33],
            ['name' => 'time_back', 'label' => 'ساعت برگشت', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:20', 'sort_order' => 34],
            ['name' => 'segments', 'label' => 'سگمنت‌های پرواز', 'type' => 'array', 'is_required' => false, 'description' => 'reservation_flight_segments: direction (outbound|return), from_city_name, to_city_name, depart_date یا depart_date_label, depart_time, airline, flight_number. شناسه تأمین‌کننده چاپ نمی‌شود.', 'sort_order' => 35],
            ['name' => 'hotel_name', 'label' => 'هتل تور', 'type' => 'string', 'is_required' => false, 'description' => 'finalHotelName. اگر خالی باشد final_hotel_name و بعد hotelName.', 'validation_rules' => 'max:180', 'sort_order' => 36],
            ['name' => 'final_hotel_name', 'label' => 'نام هتل ذخیره‌شده', 'type' => 'string', 'is_required' => false, 'description' => 'نام ستون reservations.finalHotelName.', 'validation_rules' => 'max:180', 'sort_order' => 37],
            ['name' => 'hotel_rate', 'label' => 'درجه هتل', 'type' => 'integer', 'is_required' => false, 'description' => 'reservations.hotelRate', 'min_value' => 0, 'max_value' => 7, 'sort_order' => 38],
            ['name' => 'rooms', 'label' => 'اتاق‌ها', 'type' => 'array', 'is_required' => false, 'description' => 'read_typeRooms(nameRooms): type, board یا board_label (board_name). confirmation_code پذیرفته می‌شود ولی چاپ نمی‌شود.', 'sort_order' => 39],
            ['name' => 'name_rooms', 'label' => 'متن اتاق', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.nameRooms اگر rooms ساخته نشود.', 'validation_rules' => 'max:500', 'sort_order' => 40],
            ['name' => 'days', 'label' => 'برنامه روزانه', 'type' => 'array', 'is_required' => false, 'description' => 'اختیاری. نسخه ۳ رزرو ردیف روز ذخیره نمی‌کند. {day_number, title, description, meals}', 'sort_order' => 41],
            ['name' => 'insurance', 'label' => 'بیمه همراه', 'type' => 'object', 'is_required' => false, 'description' => 'فقط اگر بیمه جدا به تور وصل است: {plan|subject, policy_number, coverage, destination}. ستون بیمه روی خود ردیف تور نیست.', 'sort_order' => 42],
            ['name' => 'services', 'label' => 'خدمات', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.services = city_tour + " / " + transfer.', 'validation_rules' => 'max:500', 'sort_order' => 43],
            ['name' => 'guide_name', 'label' => 'نام راهنما', 'type' => 'string', 'is_required' => false, 'description' => 'در نسخه ۳ ستون راهنما نیست؛ فقط اگر سایت مقدار دارد بفرستید.', 'validation_rules' => 'max:120', 'sort_order' => 44],
            ['name' => 'guide_phone', 'label' => 'تلفن راهنما', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:40', 'sort_order' => 45],
            ['name' => 'rules', 'label' => 'قوانین', 'type' => 'array', 'is_required' => false, 'description' => 'رشته‌های آماده، مثلاً متن اصلاحیه قرارداد. چاپ شناسه تأمین‌کننده ممنوع.', 'sort_order' => 46],
            ['name' => 'price_2', 'label' => 'مبلغ ارزی', 'type' => 'decimal', 'is_required' => false, 'min_value' => 0, 'sort_order' => 47],
            ['name' => 'currency_2', 'label' => 'ارز دوم', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.currency_2. پیش‌فرض استراتژی دلار است.', 'validation_rules' => 'max:10', 'sort_order' => 48],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function insuranceVariables(): array
    {
        return array_merge(self::reservationCoreVariables(), [
            ['name' => 'plan', 'label' => 'طرح بیمه', 'type' => 'string', 'is_required' => false, 'description' => 'اگر خالی باشد subject (عنوان نسخه ۳) چاپ می‌شود.', 'validation_rules' => 'max:180', 'sort_order' => 20],
            ['name' => 'policy_number', 'label' => 'شماره بیمه‌نامه', 'type' => 'string', 'is_required' => false, 'description' => 'شماره‌ای که مسافر باید داشته باشد. اگر نیست re_code. شناسه تأمین‌کننده را اینجا نگذارید.', 'validation_rules' => 'max:80', 'sort_order' => 21],
            ['name' => 'destination', 'label' => 'مقصد', 'type' => 'string', 'is_required' => false, 'description' => 'city یا city_name(cityCode). استراتژی بیمه مقصد را اجباری نمی‌کند.', 'validation_rules' => 'max:80', 'sort_order' => 22],
            ['name' => 'date_start', 'label' => 'شروع پوشش (میلادی)', 'type' => 'date', 'is_required' => false, 'sort_order' => 23],
            ['name' => 'date_end', 'label' => 'پایان پوشش (میلادی)', 'type' => 'date', 'is_required' => false, 'sort_order' => 24],
            ['name' => 'date_start_label', 'label' => 'شروع پوشش شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 25],
            ['name' => 'date_end_label', 'label' => 'پایان پوشش شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 26],
            ['name' => 'coverage', 'label' => 'شرح پوشش', 'type' => 'text', 'is_required' => false, 'description' => 'متن آماده. نسخه ۳ ستون پوشش جدا ندارد؛ از services یا یادداشت بسازید.', 'validation_rules' => 'max:2000', 'sort_order' => 27],
            ['name' => 'coverage_items', 'label' => 'بندهای پوشش', 'type' => 'array', 'is_required' => false, 'description' => 'رشته‌ها.', 'sort_order' => 28],
            ['name' => 'insured', 'label' => 'بیمه‌شدگان', 'type' => 'array', 'is_required' => false, 'description' => 'اگر خالی باشد passengers و بعد سرپرست. name یا firstName/lastName، codeMeli، birthday یا birthday_label.', 'sort_order' => 29],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function visaVariables(): array
    {
        return array_merge(self::reservationCoreVariables(), [
            ['name' => 'visa_type', 'label' => 'نوع ویزا', 'type' => 'string', 'is_required' => false, 'description' => 'ستون جدا در نسخه ۳ نیست. عنوان اصلی subject است.', 'validation_rules' => 'max:80', 'sort_order' => 20],
            ['name' => 'case_number', 'label' => 'شماره پرونده', 'type' => 'string', 'is_required' => false, 'description' => 'اگر خالی باشد re_code. شماره پرونده تأمین‌کننده را نفرستید.', 'validation_rules' => 'max:80', 'sort_order' => 21],
            ['name' => 'destination', 'label' => 'مقصد', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 22],
            ['name' => 'travel_date', 'label' => 'تاریخ سفر (میلادی)', 'type' => 'date', 'is_required' => false, 'description' => 'dateStartTour اگر پر باشد. استراتژی ویزا این تاریخ را ست نمی‌کند.', 'sort_order' => 23],
            ['name' => 'travel_date_label', 'label' => 'تاریخ سفر شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 24],
            ['name' => 'rules', 'label' => 'مدارک و قوانین', 'type' => 'array', 'is_required' => false, 'sort_order' => 25],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function cipVariables(): array
    {
        return array_merge(self::reservationCoreVariables(), [
            ['name' => 'airport', 'label' => 'فرودگاه', 'type' => 'string', 'is_required' => false, 'description' => 'متن آماده. نمونه فعلی سایت: تشریفات شهید بهشتی اصفهان.', 'validation_rules' => 'max:120', 'sort_order' => 20],
            ['name' => 'service_type', 'label' => 'کد نوع CIP', 'type' => 'string', 'is_required' => false, 'description' => 'cip_dom_to_dom_exit | cip_dom_to_int | cip_int_to_dom | cip_dom_to_dom_inter', 'validation_rules' => 'max:40', 'sort_order' => 21],
            ['name' => 'service_label', 'label' => 'نوع خدمت', 'type' => 'string', 'is_required' => false, 'description' => 'flyType(type) یا برچسب فارسی.', 'validation_rules' => 'max:80', 'sort_order' => 22],
            ['name' => 'origin', 'label' => 'مبدأ', 'type' => 'string', 'is_required' => false, 'description' => 'initialPoint', 'validation_rules' => 'max:80', 'sort_order' => 23],
            ['name' => 'destination', 'label' => 'مقصد', 'type' => 'string', 'is_required' => false, 'description' => 'city', 'validation_rules' => 'max:80', 'sort_order' => 24],
            ['name' => 'airline', 'label' => 'ایرلاین', 'type' => 'string', 'is_required' => false, 'description' => 'company_departure برای خروج، company_back برای ورود.', 'validation_rules' => 'max:80', 'sort_order' => 25],
            ['name' => 'flight_number', 'label' => 'شماره پرواز', 'type' => 'string', 'is_required' => false, 'description' => 'fly_number', 'validation_rules' => 'max:20', 'sort_order' => 26],
            ['name' => 'flight_date', 'label' => 'تاریخ پرواز (میلادی)', 'type' => 'date', 'is_required' => false, 'description' => 'checkInFly به صورت Y-m-d.', 'sort_order' => 27],
            ['name' => 'flight_date_label', 'label' => 'تاریخ پرواز شمسی', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'max:80', 'sort_order' => 28],
            ['name' => 'flight_time', 'label' => 'ساعت پرواز', 'type' => 'string', 'is_required' => false, 'description' => 'time_departure یا time_back بسته به نوع.', 'validation_rules' => 'max:20', 'sort_order' => 29],
            ['name' => 'orders', 'label' => 'سرویس مازاد', 'type' => 'array', 'is_required' => false, 'description' => '{title, count, price, total}. مبلغ هر ردیف را آماده بفرستید.', 'sort_order' => 30],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function reservationDocumentVariables(): array
    {
        return array_merge(self::reservationCoreVariables(), [
            ['name' => 'type_label', 'label' => 'برچسب نوع', 'type' => 'string', 'is_required' => false, 'description' => 'abs_reserve_kind_label(type). برای کنسلی: کنسلی رزرو.', 'validation_rules' => 'max:80', 'sort_order' => 20],
            ['name' => 'items', 'label' => 'قلم‌ها', 'type' => 'array', 'is_required' => false, 'description' => '{kind, kind_label, title, rows:[{label, value}]}. value باید متن آماده باشد. کد تأمین‌کننده را در rows نگذارید.', 'sort_order' => 21],
        ]);
    }

    /**
     * Fields shared by the show_abs documents. Amounts are display-ready.
     * V3 stores reservations.price in rial; send currency «ریال» and do not convert.
     *
     * @return list<array<string, mixed>>
     */
    private static function reservationCoreVariables(): array
    {
        return [
            ['name' => 're_code', 'label' => 'کد رزرو', 'type' => 'string', 'is_required' => true, 'description' => 'reservations.re_code. همین مقدار QR می‌شود.', 'validation_rules' => 'max:40', 'sort_order' => 1],
            ['name' => 'subject', 'label' => 'عنوان', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.subject', 'validation_rules' => 'max:180', 'sort_order' => 2],
            ['name' => 'status', 'label' => 'وضعیت', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.status مثل f_invoice یا payed.', 'validation_rules' => 'max:40', 'sort_order' => 3],
            ['name' => 'status_label', 'label' => 'برچسب وضعیت', 'type' => 'string', 'is_required' => false, 'description' => 'status_reserve(status)', 'validation_rules' => 'max:60', 'sort_order' => 4],
            ['name' => 'payment_status_label', 'label' => 'وضعیت پرداخت', 'type' => 'string', 'is_required' => false, 'description' => 'status_pay(invoice.status)', 'validation_rules' => 'max:60', 'sort_order' => 5],
            ['name' => 'invoice_number', 'label' => 'شماره فاکتور', 'type' => 'string', 'is_required' => false, 'description' => 'invoice.invoice_number', 'validation_rules' => 'max:40', 'sort_order' => 6],
            ['name' => 'issue_date', 'label' => 'تاریخ صدور (میلادی)', 'type' => 'date', 'is_required' => false, 'sort_order' => 7],
            ['name' => 'issue_date_label', 'label' => 'تاریخ صدور شمسی', 'type' => 'string', 'is_required' => false, 'description' => 'dateToJalali_date(created_at)', 'validation_rules' => 'max:80', 'sort_order' => 8],
            ['name' => 'leader_name', 'label' => 'سرپرست', 'type' => 'string', 'is_required' => false, 'description' => 'nameLeader + familyLeader. اگر خالی باشد name_leader و family_leader چسبانده می‌شوند.', 'validation_rules' => 'max:160', 'sort_order' => 9],
            ['name' => 'name_leader', 'label' => 'نام سرپرست', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.nameLeader', 'validation_rules' => 'max:80', 'sort_order' => 10],
            ['name' => 'family_leader', 'label' => 'نام خانوادگی سرپرست', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.familyLeader', 'validation_rules' => 'max:80', 'sort_order' => 11],
            ['name' => 'code_meli', 'label' => 'کد ملی سرپرست', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.codeMeli', 'validation_rules' => 'max:20', 'sort_order' => 12],
            ['name' => 'cell', 'label' => 'موبایل', 'type' => 'string', 'is_required' => false, 'description' => 'reservations.cell', 'validation_rules' => 'max:20', 'sort_order' => 13],
            ['name' => 'passenger_count', 'label' => 'تعداد مسافر', 'type' => 'integer', 'is_required' => false, 'description' => 'reservations.countPassenger', 'min_value' => 0, 'sort_order' => 14],
            ['name' => 'passengers', 'label' => 'مسافران', 'type' => 'array', 'is_required' => false, 'description' => 'Passenger با role=passenger: firstName/first_name, lastName/last_name, rangAge/rang_age یا age_group_label, codeMeli/code_meli/national_id, age, passport.', 'sort_order' => 15],
            ['name' => 'total_price', 'label' => 'مبلغ کل', 'type' => 'decimal', 'is_required' => false, 'description' => 'مبلغ نمایشی. برای نسخه ۳ همان reservations.price به ریال است. اگر خالی باشد price.', 'min_value' => 0, 'sort_order' => 16],
            ['name' => 'price', 'label' => 'مبلغ رزرو', 'type' => 'decimal', 'is_required' => false, 'description' => 'reservations.price. در نسخه ۳ واحد ریال است.', 'min_value' => 0, 'sort_order' => 17],
            ['name' => 'prepayment', 'label' => 'پیش‌پرداخت', 'type' => 'decimal', 'is_required' => false, 'description' => 'reservations.prepayment، هم‌واحد price.', 'min_value' => 0, 'sort_order' => 18],
            ['name' => 'currency', 'label' => 'واحد پول', 'type' => 'string', 'is_required' => false, 'default_value' => 'ریال', 'validation_rules' => 'max:20', 'sort_order' => 19],
            ['name' => 'payment', 'label' => 'پرداخت', 'type' => 'object', 'is_required' => false, 'description' => '{transaction_id: invoice.transaction_id, paid_at_label, status}.', 'sort_order' => 50],
            ['name' => 'notes', 'label' => 'توضیحات', 'type' => 'text', 'is_required' => false, 'description' => 'note_for_passenger یا یادداشت مسافر.', 'validation_rules' => 'max:2000', 'sort_order' => 51],
            ['name' => 'agency_name', 'label' => 'نام آژانس', 'type' => 'string', 'is_required' => false, 'default_value' => 'آژانس مسافرتی آسمان هفتم', 'validation_rules' => 'max:120', 'sort_order' => 52],
            ['name' => 'supplier_code', 'label' => 'کد تأمین‌کننده', 'type' => 'string', 'is_required' => false, 'description' => 'پذیرفته می‌شود ولی چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 90],
            ['name' => 'confirmation_code', 'label' => 'کد تأیید تأمین‌کننده', 'type' => 'string', 'is_required' => false, 'description' => 'پذیرفته می‌شود ولی چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 91],
            ['name' => 'hub_public_id', 'label' => 'شناسه هاب', 'type' => 'string', 'is_required' => false, 'description' => 'شناسه سمت تأمین‌کننده. پذیرفته می‌شود ولی چاپ نمی‌شود.', 'validation_rules' => 'max:80', 'sort_order' => 92],
        ];
    }
}
