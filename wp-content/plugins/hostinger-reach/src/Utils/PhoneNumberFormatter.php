<?php

namespace Hostinger\Reach\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PhoneNumberFormatter {
    private const E164_PATTERN = '/^\+[1-9]\d{6,14}$/';

    /* These countries have no trunk prefix: a leading zero is part of the number itself. */
    private const DIALING_CODES_KEEPING_LEADING_ZERO = array(
        'BF' => '+226',
        'BJ' => '+229',
        'CG' => '+242',
        'CI' => '+225',
        'IT' => '+39',
        'SM' => '+378',
    );

    /* These countries trunk-dial with a digit other than zero, only ever at the stated length. */
    private const NON_ZERO_TRUNK_PREFIXES = array(
        '+1'   => array(
            'digit'              => '1',
            'length_when_dialed' => 11,
        ),
        '+7'   => array(
            'digit'              => '8',
            'length_when_dialed' => 11,
        ),
        '+375' => array(
            'digit'              => '8',
            'length_when_dialed' => 11,
        ),
    );

    public function to_e164( string $phone, string $country_code ): string {
        $number = $this->to_international( $phone, $country_code );

        if ( preg_match( self::E164_PATTERN, $number ) !== 1 ) {
            return '';
        }

        return $number;
    }

    private function to_international( string $phone, string $country_code ): string {
        $cleaned = preg_replace( '/[^\d+]/', '', $this->drop_optional_trunk_digit( $phone ) );
        $digits  = str_replace( '+', '', $cleaned );

        if ( str_starts_with( $cleaned, '+' ) ) {
            return '+' . $digits;
        }

        if ( str_starts_with( $digits, '00' ) ) {
            return '+' . substr( $digits, 2 );
        }

        $dialing_code = $this->get_dialing_code( $country_code );

        if ( $dialing_code === '' ) {
            return '';
        }

        return $dialing_code . $this->drop_trunk_prefix( $digits, $dialing_code );
    }

    private function drop_optional_trunk_digit( string $phone ): string {
        return preg_replace( '/^\s*(\+\d{1,3})\s*\(0\)/', '$1', $phone );
    }

    private function drop_trunk_prefix( string $digits, string $dialing_code ): string {
        if ( $this->starts_with_dialed_trunk_digit( $digits, $dialing_code ) ) {
            $digits = substr( $digits, 1 );
        }

        if ( in_array( $dialing_code, self::DIALING_CODES_KEEPING_LEADING_ZERO, true ) ) {
            return $digits;
        }

        return ltrim( $digits, '0' );
    }

    private function starts_with_dialed_trunk_digit( string $digits, string $dialing_code ): bool {
        $trunk = self::NON_ZERO_TRUNK_PREFIXES[ $dialing_code ] ?? null;

        if ( $trunk === null ) {
            return false;
        }

        return strlen( $digits ) === $trunk['length_when_dialed'] && str_starts_with( $digits, $trunk['digit'] );
    }

    private function get_dialing_code( string $country_code ): string {
        if ( ! function_exists( 'WC' ) || empty( WC()->countries ) ) {
            return '';
        }

        $dialing_code = WC()->countries->get_country_calling_code( strtoupper( trim( $country_code ) ) );

        return is_string( $dialing_code ) ? $dialing_code : '';
    }
}
