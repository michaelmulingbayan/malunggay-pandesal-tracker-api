<?php

namespace App;

enum RiderAvailabilityStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
