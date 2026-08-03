<?php

namespace App\Reports\Money;

/**
 * The five revenue streams design D4 enumerates. This is the ONLY closed
 * set of places money can come from in the reporting layer — every
 * Summary/Timeline/Composition query iterates these cases rather than
 * inventing its own source list.
 */
enum StreamKey: string
{
    case CourseSale = 'course_sale';
    case ProductSale = 'product_sale';
    case AppointmentDeposit = 'appointment_deposit';
    case AppointmentDepositRetained = 'appointment_deposit_retained';
    case AppointmentSettlement = 'appointment_settlement';
}
