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
        ];
    }
}
