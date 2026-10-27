<?php

namespace WpDreamers\WPDDBP\Controllers\Helper;
// Do not allow directly accessing this file.
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class Helper {
    public static function get_social_links() {
        return apply_filters(
            'wpddb_doctor_social_links',
            [
                'facebook' => esc_html__('Facebook', 'doc-booker-pro'),
                'twitter' => esc_html__('Twitter', 'doc-booker-pro'),
                'instagram' => esc_html__('Instagram', 'doc-booker-pro'),
                'linkedin' => esc_html__('Linkedin', 'doc-booker-pro'),
                'pinterest' => esc_html__('Pinterest', 'doc-booker-pro'),
                'tiktok' => esc_html__('Tiktok', 'doc-booker-pro'),
                'youtube' => esc_html__('Youtube', 'doc-booker-pro'),
                'vimeo' => esc_html__('Vimeo', 'doc-booker-pro'),
                'dribble' => esc_html__('Dribble', 'doc-booker-pro'),
                'behance' => esc_html__('Behance', 'doc-booker-pro'),
                'skype' => esc_html__('Team', 'doc-booker-pro'),
                'telegram' => esc_html__('Telegram', 'doc-booker-pro'),
                'whatsapp' => esc_html__('WhatsApp', 'doc-booker-pro'),
            ]
        );
    }

    public static function get_doctor_languages() {
        return apply_filters(
            'wpddb_doctor_language_suggestions',
            [
                esc_html__('Arabic', 'doc-booker-pro'),
                esc_html__('Bengali', 'doc-booker-pro'),
                esc_html__('Cantonese', 'doc-booker-pro'),
                esc_html__('Dutch', 'doc-booker-pro'),
                esc_html__('English', 'doc-booker-pro'),
                esc_html__('Filipino', 'doc-booker-pro'),
                esc_html__('French', 'doc-booker-pro'),
                esc_html__('German', 'doc-booker-pro'),
                esc_html__('Gujarati', 'doc-booker-pro'),
                esc_html__('Hindi', 'doc-booker-pro'),
                esc_html__('Italian', 'doc-booker-pro'),
                esc_html__('Japanese', 'doc-booker-pro'),
                esc_html__('Korean', 'doc-booker-pro'),
                esc_html__('Malay', 'doc-booker-pro'),
                esc_html__('Mandarin', 'doc-booker-pro'),
                esc_html__('Polish', 'doc-booker-pro'),
                esc_html__('Portuguese', 'doc-booker-pro'),
                esc_html__('Punjabi', 'doc-booker-pro'),
                esc_html__('Russian', 'doc-booker-pro'),
                esc_html__('Spanish', 'doc-booker-pro'),
                esc_html__('Tamil', 'doc-booker-pro'),
                esc_html__('Telugu', 'doc-booker-pro'),
                esc_html__('Thai', 'doc-booker-pro'),
                esc_html__('Turkish', 'doc-booker-pro'),
                esc_html__('Urdu', 'doc-booker-pro'),
                esc_html__('Vietnamese', 'doc-booker-pro'),
            ]
        );
    }

    // Post-nominals are standardised abbreviations, so they stay untranslated —
    // localise them through the filter only where a country's letters differ.
    public static function get_doctor_credentials() {
        return apply_filters(
            'wpddb_doctor_credential_suggestions',
            [
                'MD', 'DO', 'MBBS', 'MS', 'MSc', 'PhD', 'DNB', 'FRCP', 'FRCS', 'FACC',
                'FACS', 'FACP', 'FAAP', 'ABIM', 'MRCP', 'MRCS', 'DM', 'MCh', 'BDS', 'MDS',
                'DPT', 'PharmD', 'RN', 'NP', 'PA-C',
            ]
        );
    }

    public static function get_clinic_services() {
        return apply_filters(
            'wpddb_clinic_service_suggestions',
            [
                esc_html__('Cardiology', 'doc-booker-pro'),
                esc_html__('Dentistry', 'doc-booker-pro'),
                esc_html__('Dermatology', 'doc-booker-pro'),
                esc_html__('Diagnostics', 'doc-booker-pro'),
                esc_html__('Emergency', 'doc-booker-pro'),
                esc_html__('ENT', 'doc-booker-pro'),
                esc_html__('Gastroenterology', 'doc-booker-pro'),
                esc_html__('General Medicine', 'doc-booker-pro'),
                esc_html__('Gynecology', 'doc-booker-pro'),
                esc_html__('Intensive Care', 'doc-booker-pro'),
                esc_html__('Laboratory', 'doc-booker-pro'),
                esc_html__('Maternity', 'doc-booker-pro'),
                esc_html__('Neurology', 'doc-booker-pro'),
                esc_html__('Oncology', 'doc-booker-pro'),
                esc_html__('Ophthalmology', 'doc-booker-pro'),
                esc_html__('Orthopedics', 'doc-booker-pro'),
                esc_html__('Pediatrics', 'doc-booker-pro'),
                esc_html__('Pharmacy', 'doc-booker-pro'),
                esc_html__('Physiotherapy', 'doc-booker-pro'),
                esc_html__('Psychiatry', 'doc-booker-pro'),
                esc_html__('Pulmonology', 'doc-booker-pro'),
                esc_html__('Radiology', 'doc-booker-pro'),
                esc_html__('Rehabilitation', 'doc-booker-pro'),
                esc_html__('Surgery', 'doc-booker-pro'),
                esc_html__('Urology', 'doc-booker-pro'),
                esc_html__('Vaccination', 'doc-booker-pro'),
            ]
        );
    }

    public static function has_online_payment() {
        $online_booking_payment = WpddbOptions::get_option('online_booking_payment','wpddb_global_settings') ? :'on';
        if ( $online_booking_payment === 'on' ) {
            return true;
        }
        return false;
    }
    public static function get_social_icon_svg( $social_name ) {
        switch (  $social_name  ) {
            case 'facebook':
                return '<svg width="16" height="16" viewBox="0 0 320 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M279.14 288l14.22-92.66h-88.91V134.58c0-25.35 12.42-50.06 
                        52.24-50.06H293V6.26S259.36 0 225.36 0C141.09 0 
                        89.53 54.42 89.53 153.12v68.22H0v92.66h89.53V512h107.78V288z"/>
                    </svg>';

            case 'twitter':
                return '<svg width="16" height="16" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M459.4 151.7c.3 4.1 .3 8.2 .3 12.3 0 125.3-95.4 269.6-269.6 269.6-53.7 0-103.7-15.7-145.7-42.7 7.4 .9 14.8 1.2 22.5 1.2 44.6 0 85.5-15.2 118-40.9 -41.7-.9-76.8-28.3-89-66.2 5.8 .9 11.4 1.5 17.4 1.5 8.3 0 16.3-1.2 23.8-3.2 -43.8-8.7-76.6-47.3-76.6-93.5 v-1.2c12.9 7.2 27.7 11.5 43.4 12 -25.7-17.1-42.6-46.4-42.6-79.4 0-17.4 4.6-33.7 12.9-47.7 47.1 57.8 117.5 95.6 196.7 99.7-1.5-7-2.4-14.3-2.4-21.7 0-52.7 42.7-95.4 95.4-95.4 27.4 0 52.2 11.5 69.6 30.3 21.7-4.1 42.6-12.3 61.3-23.4 -7.2 22.5-22.5 41.5-42.6 53.4 19.2-2.1 37.8-7.4 55-15 -12.6 19.2-28.3 36.3-46.5 49.9z"/>
                    </svg>';

            case 'linkedin':
                return '<svg width="16" height="16" viewBox="0 0 448 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100.28 448H7.4V148.9h92.88zm-46.44-341C24.1 107 0 82.4 0 53.6 0 24.4 24.8 0 55.4 0s55.4 24.4 55.4 53.6c0 28.8-24.1 53.4-55.4 53.4zM447.9 448h-92.4V302.4c0-34.7-.7-79.2-48.3-79.2-48.3 0-55.7 37.7-55.7 76.7V448h-92.4V148.9h88.7v40.8h1.3c12.4-23.5 42.6-48.3 87.7-48.3 93.8 0 111.1 61.7 111.1 141.9V448z"/>
                    </svg>';

            case 'pinterest':
                return '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="12"  fill="currentColor" viewBox="0 0 384 512"><path d="M204 6.5C101.4 6.5 0 74.9 0 185.6 0 256 39.6 296 63.6 296c9.9 0 15.6-27.6 15.6-35.4 0-9.3-23.7-29.1-23.7-67.8 0-80.4 61.2-137.4 140.4-137.4 68.1 0 118.5 38.7 118.5 109.8 0 53.1-21.3 152.7-90.3 152.7-24.9 0-46.2-18-46.2-43.8 0-37.8 26.4-74.4 26.4-113.4 0-66.2-93.9-54.2-93.9 25.8 0 16.8 2.1 35.4 9.6 50.7-13.8 59.4-42 147.9-42 209.1 0 18.9 2.7 37.5 4.5 56.4 3.4 3.8 1.7 3.4 6.9 1.5 50.4-69 48.6-82.5 71.4-172.8 12.3 23.4 44.1 36 69.3 36 106.2 0 153.9-103.5 153.9-196.8C384 71.3 298.2 6.5 204 6.5z"/></svg>';

            case 'instagram':
                return '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="14" fill="currentColor" viewBox="0 0 448 512"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg>';

            case 'youtube':
                return '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" height="16" width="18" viewBox="0 0 576 512"><path d="M549.7 124.1c-6.3-23.7-24.8-42.3-48.3-48.6C458.8 64 288 64 288 64S117.2 64 74.6 75.5c-23.5 6.3-42 24.9-48.3 48.6-11.4 42.9-11.4 132.3-11.4 132.3s0 89.4 11.4 132.3c6.3 23.7 24.8 41.5 48.3 47.8C117.2 448 288 448 288 448s170.8 0 213.4-11.5c23.5-6.3 42-24.2 48.3-47.8 11.4-42.9 11.4-132.3 11.4-132.3s0-89.4-11.4-132.3zm-317.5 213.5V175.2l142.7 81.2-142.7 81.2z"/></svg>';

            case 'tiktok':
                return '<svg xmlns="http://www.w3.org/2000/svg" height="16" fill="currentColor" width="14" viewBox="0 0 448 512"><path d="M448 209.9a210.1 210.1 0 0 1 -122.8-39.3V349.4A162.6 162.6 0 1 1 185 188.3V278.2a74.6 74.6 0 1 0 52.2 71.2V0l88 0a121.2 121.2 0 0 0 1.9 22.2h0A122.2 122.2 0 0 0 381 102.4a121.4 121.4 0 0 0 67 20.1z"/></svg>';

            case 'vimeo':
                return '<svg width="16" height="18" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M476.2 131.5c-3.5 77.6-57.6 184.6-162.2 318.7-108.1 138-177.9 208.5-209.5 211.6-14 1.3-25.6-4.1-34.8-16.1s-13.9-21.7-14.7-28.4c-1.3-12.9-7.5-62.7-18.7-148.2-10.2-81-15.2-131.5-15.2-151.6 0-35.3 7.9-65.5 23.7-90.7 15.7-25.2 36.5-37.8 62.1-37.8 21.7 0 39.3 8 52.7 23.9 13.4 15.9 20.4 34.4 20.9 55.5 0.2 6.6-1.5 17.7-5 33.4s-5.6 26.7-6.5 30.2c-4.3 17.2-9 32.2-13.9 44.8-6.6 15.7-14.3 23.6-23.1 23.6-5.8 0-10.9-5.4-15.4-16.1s-6.8-19.5-6.8-26.5 c0-25.2 3.8-49.2 11.3-72 10.2-30.2 24.3-45.3 42.2-45.3 12.5 0 22.6 4.6 30.4 13.7s11.7 20.8 11.7 35.1 c0 14.5-4.2 32.5-12.5 54.1 -8.3 21.6-12.5 33.7-12.5 36.4 0 1.6.9 2.4 2.6 2.4 7.8 0 22.2-13.4 43.2-40.1 21-26.7 31.4-47.4 31.4-62 0-11.9-4.4-22.2-13.3-30.8 -8.9-8.6-19.7-12.9-32.5-12.9 -7.1 0-13.5.6-19.3 1.9 -1.5.3-3.4-.1-5.8-1.2s-4.5-2.6-6.3-4.5c-1.8-1.9-3.3-3.6-4.6-5 -1.3-1.4-2.2-2.2-2.7-2.5 -10.2-4.8-18.5-7.2-24.8-7.2 -11.4 0-17.1 4.6-17.1 13.8 0 2.2.5 4.9 1.6 8.3 1.1 3.4 1.6 5.8 1.6 7.2 0 8.4-4.2 12.6-12.5 12.6 -2.9 0-6.1-.8-9.5-2.3 -3.4-1.5-5.3-2.5-5.8-3z"/>
                    </svg>';
            case 'whatsapp':
                return '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="14" fill="currentColor" viewBox="0 0 448 512"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7 .9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>';

            case 'dribble':
                return '<svg width="16" height="16" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm163.9 114.5c27.9 36.4 44.5 81.9 44.5 131.5 0 5.3-.2 10.6-.6 15.8-34.8-6.9-66.3-7.7-94.8-2.6-3-7.4-6.1-14.6-9.4-21.7 46.4-18.5 79.4-48.2 100.3-84zm-25.8-31.7c-18.6 31.7-47.4 58-85.3 76.3-22.3-41.3-47.3-79.5-74.7-114.6 58.4 4.4 110.1 30.9 146 72.3zM256 48c41.6 0 80 12.7 111.6 34.4-24.5 40.8-60.7 68.8-107.6 86.2-31.2-57.2-66.5-105.2-85.5-127.2 26.2-7.4 53.9-11.4 81.5-11.4zm-107.3 19.2c16.7 18.2 52.4 64.5 86.7 124.2-64.5 19.3-131.2 19.5-193.4 1.7 21.1-54.3 63.8-97.4 116.7-125.9zm-124 163.4c73.1 23.3 149.5 22.9 223.6-1.1 3.9 7.7 7.6 15.6 11 23.6-76.4 23.2-126.3 67.7-161.4 109.4-41-36.5-67.6-89.6-73.2-146.5zm85.1 164.3c30.9-40.2 76.4-79.1 147.1-99.9 16.9 39.9 30.4 82.7 38.6 127.6-70.7 21.4-139.8 11.7-185.7-27.7zm204.2 20.9c-7.2-39.8-18.3-78.2-32.3-115.2 24.5-4.1 52.3-3.6 84.9 1.5-6.9 49.3-30.4 93.2-65.5 113.7 4.3 0 8.5 0 12.9-.1zm-50.9 3.3c-3.8-22.1-9.4-44.2-16.4-66.1 10.6 19.9 18.8 40.5 23.8 61.5-2.4 1.5-5 2.9-7.4 4.6z"/>
            </svg>';

            case 'behance':
                return '<svg width="20" height="24" viewBox="0 0 576 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M232 237.5c0 22.3-14.5 31.6-38.4 31.6H152v-63.2h41.6c23.8 0 38.4 9.5 38.4 31.6zM230.3 315.3c0 24.9-17.4 35.6-42.4 35.6H152v-71.1h35.9c25.1 0 42.4 9.6 42.4 35.5zm85.9-187.1h106.7v32.5H316.2v-32.5zm-316.2 320h191.6c77.3 0 116.4-37.2 116.4-90.2 0-39.5-25.3-64.6-61.4-71.7 25.7-9.1 45.8-31.6 45.8-64.5 0-47.2-38.4-77.2-102-77.2H0v303.6zm360.9-143.7c0 61.3 37.6 96.3 94.3 96.3 44.1 0 71.3-16.5 85.4-48.3l-41.5-17.3c-6.5 16.7-21.3 24.3-43.9 24.3-27.2 0-45.6-14.6-48.2-41.3h136.6c5.2-55.7-25.5-108.2-94.3-108.2-57.2 0-88.4 42.3-88.4 94.5zm47.4-28.3c2.6-20.6 15.2-37.8 40.6-37.8 25.3 0 37.7 16.9 38.7 37.8H408.3z"/>
            </svg>';
            case 'skype':
                return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 22 21"><path d="M21.6032 19.1499C20.0564 16.4758 17.6727 14.5583 14.8909 13.6493C16.2669 12.8301 17.336 11.582 17.9339 10.0964C18.5319 8.61089 18.6257 6.97014 18.2009 5.42614C17.7761 3.88214 16.8562 2.52027 15.5825 1.54967C14.3088 0.579069 12.7517 0.0534058 11.1504 0.0534058C9.54899 0.0534058 7.9919 0.579069 6.7182 1.54967C5.4445 2.52027 4.52462 3.88214 4.09983 5.42614C3.67504 6.97014 3.76883 8.61089 4.36678 10.0964C4.96474 11.582 6.03381 12.8301 7.40981 13.6493C4.62802 14.5573 2.24434 16.4748 0.697548 19.1499C0.640824 19.2424 0.6032 19.3453 0.586894 19.4526C0.570589 19.5599 0.575933 19.6693 0.602612 19.7745C0.62929 19.8796 0.676762 19.9784 0.742227 20.0649C0.807692 20.1515 0.889824 20.224 0.983776 20.2783C1.07773 20.3325 1.1816 20.3674 1.28926 20.3809C1.39692 20.3944 1.50619 20.3862 1.61062 20.3567C1.71505 20.3273 1.81252 20.2772 1.89729 20.2095C1.98206 20.1418 2.05241 20.0578 2.10419 19.9624C4.01763 16.6555 7.39966 14.6812 11.1504 14.6812C14.9011 14.6812 18.2831 16.6555 20.1965 19.9624C20.2483 20.0578 20.3187 20.1418 20.4034 20.2095C20.4882 20.2772 20.5857 20.3273 20.6901 20.3567C20.7945 20.3862 20.9038 20.3944 21.0115 20.3809C21.1191 20.3674 21.223 20.3325 21.3169 20.2783C21.4109 20.224 21.493 20.1515 21.5585 20.0649C21.624 19.9784 21.6714 19.8796 21.6981 19.7745C21.7248 19.6693 21.7301 19.5599 21.7138 19.4526C21.6975 19.3453 21.6599 19.2424 21.6032 19.1499ZM5.46286 7.36867C5.46286 6.24379 5.79643 5.14417 6.42138 4.20886C7.04633 3.27356 7.93459 2.54458 8.97385 2.1141C10.0131 1.68363 11.1567 1.571 12.2599 1.79045C13.3632 2.00991 14.3766 2.55159 15.172 3.347C15.9674 4.14241 16.5091 5.15583 16.7286 6.25909C16.948 7.36236 16.8354 8.50593 16.4049 9.54518C15.9745 10.5844 15.2455 11.4727 14.3102 12.0977C13.3749 12.7226 12.2752 13.0562 11.1504 13.0562C9.64244 13.0546 8.19674 12.4548 7.13047 11.3886C6.06421 10.3223 5.46447 8.87659 5.46286 7.36867Z"/></svg>';
            case 'telegram':
                return '<svg width="16" height="16" viewBox="0 0 496 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M248,8C111.03,8,0,119.03,0,256s111.03,248,248,248s248-111.03,248-248S384.97,8,248,8z M371.13,169.21
                                l-45.6,214.7c-3.45,15.43-12.56,19.24-25.46,12.01l-70.31-51.86l-33.94,32.67c-3.75,3.75-6.9,6.9-14.1,6.9l5.03-71.27
                                l129.7-117.12c5.63-5.03-1.25-7.84-8.74-2.81L163.34,274.3l-69.06-21.6c-15.01-4.69-15.28-15.01,3.15-22.23l268.01-103.35
                                C373.11,122.1,378.56,130.24,371.13,169.21z"/>
                        </svg>';
            default:
                return '<svg width="16" height="16" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="256" cy="256" r="248"/>
                    </svg>';
        }
    }
    public static function render_number_pagination($current_page, $total_pages) {
        $html = '<div class="number-pagination">';

        $prev_disabled = $current_page <= 1 ? 'disabled' : '';
        $prev_page = max(1, $current_page - 1);

        $html .= '<a href="#" class="page-link prev-link ' . $prev_disabled . '" data-page="' . $prev_page . '">';
        $html .= '<span class="page-link-icon">‹</span>';
        $html .= '<span class="page-link-text">' . esc_html__( 'Previous', 'doc-booker-pro' ) . '</span>';
        $html .= '</a>';


        $max_visible = 7;
        $start_page = max(1, $current_page - floor($max_visible / 2));
        $end_page = min($total_pages, $start_page + $max_visible - 1);


        if ($end_page - $start_page + 1 < $max_visible) {
            $start_page = max(1, $end_page - $max_visible + 1);
        }

        if ($start_page > 1) {
            $html .= '<a href="#" class="page-link" data-page="1">1</a>';
            if ($start_page > 2) {
                $html .= '<span class="page-ellipsis">...</span>';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            $current_class = $i === $current_page ? 'current' : '';
            $html .= '<a href="#" class="page-link ' . $current_class . '" data-page="' . $i . '">' . $i . '</a>';
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $html .= '<span class="page-ellipsis">...</span>';
            }
            $html .= '<a href="#" class="page-link" data-page="' . $total_pages . '">' . $total_pages . '</a>';
        }

        $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
        $next_page = min($total_pages, $current_page + 1);

        $html .= '<a href="#" class="page-link next-link ' . $next_disabled . '" data-page="' . $next_page . '">';
        $html .= '<span class="page-link-text">' . esc_html__( 'Next', 'doc-booker-pro' ) . '</span>';
        $html .= '<span class="page-link-icon">›</span>';
        $html .= '</a>';

        $html .= '</div>';

        return $html;
    }
    public static function render_load_more_pagination($current_page, $total_pages, $post_type_label) {
        $html = '<div class="load-more-pagination">';

        if ($current_page < $total_pages) {
            $button_text = 'Load More ' . ucfirst($post_type_label);
            $disabled_class = '';
            $disabled_attr = '';
        } else {
            $button_text = 'No More Results';
            $disabled_class = 'disabled';
            $disabled_attr = 'disabled';
        }

        $html .= '<button class="load-more-btn ' . $disabled_class . '" ' . $disabled_attr . '>';
        $html .= $button_text;
        $html .= '</button>';

        $html .= '</div>';

        return $html;
    }
    public static function render_infinite_scroll_indicator() {
        $html = '<div class="infinite-scroll-pagination">';
        $html .= '<div class="infinite-scroll-indicator" style="display: none;">';
        $html .= '<div class="loading-spinner">';
        $html .= '<div class="spinner"></div>';
        $html .= '<span class="loading-text">'.esc_html__('Loading more...', 'doc-booker-pro').'</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
    public static function sanitize_custom_img_size( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();

        if ( isset( $value['width'] ) ) {
            $sanitized['width'] = intval( $value['width'] );
        }

        if ( isset( $value['height'] ) ) {
            $sanitized['height'] = intval( $value['height'] );
        }

        if ( isset( $value['crop'] ) ) {
            $sanitized['crop'] = sanitize_text_field( $value['crop'] );
        }

        return $sanitized;
    }
    public static function display_custom_gallery($post_id) {
        $gallery_images = get_post_meta($post_id, 'wpddb_clinic_gallery_images', true);

        if (empty($gallery_images) || !is_array($gallery_images)) {
            return '';
        }

        $images_data = array();
        $display_limit = 4;
        $total_images = count($gallery_images);
        foreach ($gallery_images as $index => $image_url) {

            if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $image_extension = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
            $valid_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg');

            if (!in_array($image_extension, $valid_extensions)) {
                continue;
            }

            $attachment_id = attachment_url_to_postid($image_url);
            $image_alt = '';

            if ($attachment_id) {
                $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                if (empty($image_alt)) {
                    $image_alt = get_the_title($attachment_id);
                }
            }

            if (empty($image_alt)) {
                $image_alt = 'Gallery image ' . ($index + 1);
            }

            $images_data[] = array(
                'thumb' => $image_url,
                'full' => $image_url,
                'width' => 1200,
                'height' => 800,
                'alt' => $image_alt
            );
        }

        if (empty($images_data)) {
            return '';
        }

        $total_images = count($images_data);

        ob_start();
        ?>
        <div class="custom-gallery" data-total="<?php echo esc_attr($total_images); ?>" data-gallery="<?php echo esc_attr(json_encode($images_data)); ?>">
            <div class="gallery-preview">
                <?php foreach (array_slice($images_data, 0, $display_limit) as $index => $image): ?>
                    <div class="gallery-item <?php echo ($index === 3 && $total_images > 4) ? 'has-more' : ''; ?>"
                         data-index="<?php echo esc_attr($index); ?>">
                        <img src="<?php echo esc_url($image['thumb']); ?>"
                             alt="<?php echo esc_attr($image['alt']); ?>"
                             loading="lazy">
                        <?php if ($index === 3 && $total_images > 4): ?>
                            <div class="more-overlay">
                                <?php echo esc_html( sprintf( /* translators: %d: number of further gallery images */ __( '+%d more', 'doc-booker-pro' ), $total_images - 4 ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    public static function display_clinic_slider_gallery($post_id) {
        $gallery_images = get_post_meta($post_id, 'wpddb_clinic_gallery_images', true);

        if (empty($gallery_images) || !is_array($gallery_images)) {
            return '';
        }
        ob_start();
        $slider_settings = wp_json_encode(self::get_clinic_slider_settings());
        ?>
        <div class="custom-gallery">
            <div class="swiper wpddb-doc-booker-global-slider" data-slider="<?php echo esc_attr($slider_settings); ?>">
                <div class="swiper-wrapper">
                    <?php foreach ($gallery_images as $index => $image_url): ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url($image_url); ?>"
                                 alt="<?php esc_attr_e( 'clinic gallery image', 'doc-booker-pro' ); ?>"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-nav">
                    <div class="swiper-arrow swiper-button-prev"></div>
                    <div class="swiper-arrow swiper-button-next"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    public static function get_clinic_slider_settings() {
        $slides_per_view = WpddbOptions::get_option( 'slides_per_view', 'wpddb_clinic_settings' ) ?: '3';
        $slider_autoplay = WpddbOptions::get_option( 'slider_autoplay', 'wpddb_clinic_settings' ) === 'on';
        $slider_loop     = WpddbOptions::get_option( 'slider_loop', 'wpddb_clinic_settings' ) === 'on';
        $centered_slider = WpddbOptions::get_option( 'centered_slider', 'wpddb_clinic_settings' ) === 'on';
        $slider_settings = array(
            'slidesPerView'       => $slides_per_view,
            'loop'                => $slider_loop,
            'spaceBetween'        => 20,
            'slidesPerGroup'      => 1,
            'centeredSlides'      => $centered_slider,
            'slideToClickedSlide' => true,
            'autoplay'            => array(
                'delay' => 2000,
            ),
            'speed'               => 2000,
            'breakpoints'         => array(
                '0'    => array( 'slidesPerView' => 1 ),
                '576'  => array( 'slidesPerView' => 1 ),
                '768'  => array( 'slidesPerView' => 1 ),
                '992'  => array( 'slidesPerView' => 2 ),
                '1200' => array( 'slidesPerView' => 2 ),
                '1600' => array( 'slidesPerView' => $slides_per_view )
            ),
            'auto'                => $slider_autoplay
        );

        return apply_filters( 'wpddbp_clinic_slider_settings', $slider_settings );
    }
    public static function currency_list() {
        return apply_filters(
            'wpddbp_currency_list',
            [
                'AED' => [
                    'code'           => 'AED',
                    'symbol'         => 'د.إ',
                    'name'           => 'United Arab Emirates Dirham',
                    'numeric_code'   => '784',
                    'code_placement' => 'before',
                    'minor_unit'     => 'Fils',
                    'major_unit'     => 'Dirham',
                ],
                'AFN' => [
                    'code'         => 'AFN',
                    'symbol'       => 'Af',
                    'name'         => 'Afghan Afghani',
                    'decimals'     => 0,
                    'numeric_code' => '971',
                    'minor_unit'   => 'Pul',
                    'major_unit'   => 'Afghani',
                ],
                'ANG' => [
                    'code'         => 'ANG',
                    'symbol'       => 'NAf.',
                    'name'         => 'Netherlands Antillean Guilder',
                    'numeric_code' => '532',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Guilder',
                ],
                'AOA' => [
                    'code'         => 'AOA',
                    'symbol'       => 'Kz',
                    'name'         => 'Angolan Kwanza',
                    'numeric_code' => '973',
                    'minor_unit'   => 'Cêntimo',
                    'major_unit'   => 'Kwanza',
                ],
                'ARM' => [
                    'code'       => 'ARM',
                    'symbol'     => 'm$n',
                    'name'       => 'Argentine Peso Moneda Nacional',
                    'minor_unit' => 'Centavos',
                    'major_unit' => 'Peso',
                ],
                'ARS' => [
                    'code'         => 'ARS',
                    'symbol'       => 'AR$',
                    'name'         => 'Argentine Peso',
                    'numeric_code' => '032',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Peso',
                ],
                'AUD' => [
                    'code'             => 'AUD',
                    'symbol'           => '$',
                    'name'             => 'Australian Dollar',
                    'numeric_code'     => '036',
                    'symbol_placement' => 'before',
                    'minor_unit'       => 'Cent',
                    'major_unit'       => 'Dollar',
                ],
                'AWG' => [
                    'code'         => 'AWG',
                    'symbol'       => 'Afl.',
                    'name'         => 'Aruban Florin',
                    'numeric_code' => '533',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Guilder',
                ],
                'AZN' => [
                    'code'       => 'AZN',
                    'symbol'     => 'man.',
                    'name'       => 'Azerbaijanian Manat',
                    'minor_unit' => 'Qəpik',
                    'major_unit' => 'New Manat',
                ],
                'BAM' => [
                    'code'         => 'BAM',
                    'symbol'       => 'KM',
                    'name'         => 'Bosnia-Herzegovina Convertible Mark',
                    'numeric_code' => '977',
                    'minor_unit'   => 'Fening',
                    'major_unit'   => 'Convertible Marka',
                ],
                'BBD' => [
                    'code'         => 'BBD',
                    'symbol'       => 'Bds$',
                    'name'         => 'Barbadian Dollar',
                    'numeric_code' => '052',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'BDT' => [
                    'code'         => 'BDT',
                    'symbol'       => 'Tk',
                    'name'         => 'Bangladeshi Taka',
                    'numeric_code' => '050',
                    'minor_unit'   => 'Paisa',
                    'major_unit'   => 'Taka',
                ],
                'BGN' => [
                    'code'                => 'BGN',
                    'symbol'              => 'лв',
                    'name'                => 'Bulgarian lev',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'numeric_code'        => '975',
                    'minor_unit'          => 'Stotinka',
                    'major_unit'          => 'Lev',
                ],
                'BHD' => [
                    'code'         => 'BHD',
                    'symbol'       => 'BD',
                    'name'         => 'Bahraini Dinar',
                    'decimals'     => 3,
                    'numeric_code' => '048',
                    'minor_unit'   => 'Fils',
                    'major_unit'   => 'Dinar',
                ],
                'BIF' => [
                    'code'         => 'BIF',
                    'symbol'       => 'FBu',
                    'name'         => 'Burundian Franc',
                    'decimals'     => 0,
                    'numeric_code' => '108',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'BMD' => [
                    'code'         => 'BMD',
                    'symbol'       => 'BD$',
                    'name'         => 'Bermudan Dollar',
                    'numeric_code' => '060',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'BND' => [
                    'code'         => 'BND',
                    'symbol'       => 'BN$',
                    'name'         => 'Brunei Dollar',
                    'numeric_code' => '096',
                    'minor_unit'   => 'Sen',
                    'major_unit'   => 'Dollar',
                ],
                'BOB' => [
                    'code'         => 'BOB',
                    'symbol'       => 'Bs',
                    'name'         => 'Bolivian Boliviano',
                    'numeric_code' => '068',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Bolivianos',
                ],
                'BRL' => [
                    'code'                => 'BRL',
                    'symbol'              => 'R$',
                    'name'                => 'Brazilian Real',
                    'numeric_code'        => '986',
                    'symbol_placement'    => 'before',
                    'code_placement'      => 'hidden',
                    'thousands_separator' => '.',
                    'decimal_separator'   => ',',
                    'minor_unit'          => 'Centavo',
                    'major_unit'          => 'Reais',
                ],
                'BSD' => [
                    'code'         => 'BSD',
                    'symbol'       => 'BS$',
                    'name'         => 'Bahamian Dollar',
                    'numeric_code' => '044',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'BTN' => [
                    'code'         => 'BTN',
                    'symbol'       => 'Nu.',
                    'name'         => 'Bhutanese Ngultrum',
                    'numeric_code' => '064',
                    'minor_unit'   => 'Chetrum',
                    'major_unit'   => 'Ngultrum',
                ],
                'BWP' => [
                    'code'         => 'BWP',
                    'symbol'       => 'BWP',
                    'name'         => 'Botswanan Pula',
                    'numeric_code' => '072',
                    'minor_unit'   => 'Thebe',
                    'major_unit'   => 'Pulas',
                ],
                'BYR' => [
                    'code'                => 'BYR',
                    'symbol'              => 'руб.',
                    'name'                => 'Belarusian ruble',
                    'numeric_code'        => '974',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'decimals'            => 0,
                    'thousands_separator' => ' ',
                    'major_unit'          => 'Ruble',
                ],
                'BZD' => [
                    'code'         => 'BZD',
                    'symbol'       => 'BZ$',
                    'name'         => 'Belize Dollar',
                    'numeric_code' => '084',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'CAD' => [
                    'code'         => 'CAD',
                    'symbol'       => 'CA$',
                    'name'         => 'Canadian Dollar',
                    'numeric_code' => '124',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'CDF' => [
                    'code'         => 'CDF',
                    'symbol'       => 'CDF',
                    'name'         => 'Congolese Franc',
                    'numeric_code' => '976',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'CHF' => [
                    'code'          => 'CHF',
                    'symbol'        => 'Fr.',
                    'name'          => 'Swiss Franc',
                    'rounding_step' => '0.05',
                    'numeric_code'  => '756',
                    'minor_unit'    => 'Rappen',
                    'major_unit'    => 'Franc',
                ],
                'CLP' => [
                    'code'         => 'CLP',
                    'symbol'       => 'CL$',
                    'name'         => 'Chilean Peso',
                    'decimals'     => 0,
                    'numeric_code' => '152',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Peso',
                ],
                'CNY' => [
                    'code'                => 'CNY',
                    'symbol'              => '¥',
                    'name'                => 'Chinese Yuan Renminbi',
                    'numeric_code'        => '156',
                    'symbol_placement'    => 'before',
                    'code_placement'      => 'hidden',
                    'thousands_separator' => '',
                    'minor_unit'          => 'Fen',
                    'major_unit'          => 'Yuan',
                ],
                'COP' => [
                    'code'                => 'COP',
                    'symbol'              => '$',
                    'name'                => 'Colombian Peso',
                    'decimals'            => 0,
                    'numeric_code'        => '170',
                    'symbol_placement'    => 'before',
                    'code_placement'      => 'hidden',
                    'thousands_separator' => '.',
                    'decimal_separator'   => ',',
                    'minor_unit'          => 'Centavo',
                    'major_unit'          => 'Peso',
                ],
                'CRC' => [
                    'code'         => 'CRC',
                    'symbol'       => '¢',
                    'name'         => 'Costa Rican Colón',
                    'decimals'     => 0,
                    'numeric_code' => '188',
                    'minor_unit'   => 'Céntimo',
                    'major_unit'   => 'Colón',
                ],
                'CUC' => [
                    'code'       => 'CUC',
                    'symbol'     => 'CUC$',
                    'name'       => 'Cuban Convertible Peso',
                    'minor_unit' => 'Centavo',
                    'major_unit' => 'Peso',
                ],
                'CUP' => [
                    'code'         => 'CUP',
                    'symbol'       => 'CU$',
                    'name'         => 'Cuban Peso',
                    'numeric_code' => '192',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Peso',
                ],
                'CVE' => [
                    'code'         => 'CVE',
                    'symbol'       => 'CV$',
                    'name'         => 'Cape Verdean Escudo',
                    'numeric_code' => '132',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Escudo',
                ],
                'CZK' => [
                    'code'                => 'CZK',
                    'symbol'              => 'Kč',
                    'name'                => 'Czech Republic Koruna',
                    'numeric_code'        => '203',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Haléř',
                    'major_unit'          => 'Koruna',
                ],
                'DJF' => [
                    'code'         => 'DJF',
                    'symbol'       => 'Fdj',
                    'name'         => 'Djiboutian Franc',
                    'numeric_code' => '262',
                    'decimals'     => 0,
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'DKK' => [
                    'code'                => 'DKK',
                    'symbol'              => 'kr.',
                    'name'                => 'Danish Krone',
                    'numeric_code'        => '208',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Øre',
                    'major_unit'          => 'Kroner',
                ],
                'DOP' => [
                    'code'         => 'DOP',
                    'symbol'       => 'RD$',
                    'name'         => 'Dominican Peso',
                    'numeric_code' => '214',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Peso',
                ],
                'DZD' => [
                    'code'         => 'DZD',
                    'symbol'       => 'DA',
                    'name'         => 'Algerian Dinar',
                    'numeric_code' => '012',
                    'minor_unit'   => 'Santeem',
                    'major_unit'   => 'Dinar',
                ],
                'EEK' => [
                    'code'                => 'EEK',
                    'symbol'              => 'Ekr',
                    'name'                => 'Estonian Kroon',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'numeric_code'        => '233',
                    'minor_unit'          => 'Sent',
                    'major_unit'          => 'Krooni',
                ],
                'EGP' => [
                    'code'         => 'EGP',
                    'symbol'       => 'EG£',
                    'name'         => 'Egyptian Pound',
                    'numeric_code' => '818',
                    'minor_unit'   => 'Piastr',
                    'major_unit'   => 'Pound',
                ],
                'ERN' => [
                    'code'         => 'ERN',
                    'symbol'       => 'Nfk',
                    'name'         => 'Eritrean Nakfa',
                    'numeric_code' => '232',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Nakfa',
                ],
                'ETB' => [
                    'code'         => 'ETB',
                    'symbol'       => 'Br',
                    'name'         => 'Ethiopian Birr',
                    'numeric_code' => '230',
                    'minor_unit'   => 'Santim',
                    'major_unit'   => 'Birr',
                ],
                'EUR' => [
                    'code'                => 'EUR',
                    'symbol'              => '€',
                    'name'                => 'Euro',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'numeric_code'        => '978',
                    'minor_unit'          => 'Cent',
                    'major_unit'          => 'Euro',
                ],
                'FJD' => [
                    'code'         => 'FJD',
                    'symbol'       => 'FJ$',
                    'name'         => 'Fijian Dollar',
                    'numeric_code' => '242',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'FKP' => [
                    'code'         => 'FKP',
                    'symbol'       => 'FK£',
                    'name'         => 'Falkland Islands Pound',
                    'numeric_code' => '238',
                    'minor_unit'   => 'Penny',
                    'major_unit'   => 'Pound',
                ],
                'GBP' => [
                    'code'             => 'GBP',
                    'symbol'           => '£',
                    'name'             => 'British Pound Sterling',
                    'numeric_code'     => '826',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Penny',
                    'major_unit'       => 'Pound',
                ],
                'GHS' => [
                    'code'       => 'GHS',
                    'symbol'     => 'GH₵',
                    'name'       => 'Ghanaian Cedi',
                    'minor_unit' => 'Pesewa',
                    'major_unit' => 'Cedi',
                ],
                'GIP' => [
                    'code'         => 'GIP',
                    'symbol'       => 'GI£',
                    'name'         => 'Gibraltar Pound',
                    'numeric_code' => '292',
                    'minor_unit'   => 'Penny',
                    'major_unit'   => 'Pound',
                ],
                'GMD' => [
                    'code'         => 'GMD',
                    'symbol'       => 'GMD',
                    'name'         => 'Gambian Dalasi',
                    'numeric_code' => '270',
                    'minor_unit'   => 'Butut',
                    'major_unit'   => 'Dalasis',
                ],
                'GNF' => [
                    'code'         => 'GNF',
                    'symbol'       => 'FG',
                    'name'         => 'Guinean Franc',
                    'decimals'     => 0,
                    'numeric_code' => '324',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'GTQ' => [
                    'code'         => 'GTQ',
                    'symbol'       => 'GTQ',
                    'name'         => 'Guatemalan Quetzal',
                    'numeric_code' => '320',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Quetzales',
                ],
                'GYD' => [
                    'code'         => 'GYD',
                    'symbol'       => 'GY$',
                    'name'         => 'Guyanaese Dollar',
                    'decimals'     => 0,
                    'numeric_code' => '328',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'HKD' => [
                    'code'             => 'HKD',
                    'symbol'           => 'HK$',
                    'name'             => 'Hong Kong Dollar',
                    'numeric_code'     => '344',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Cent',
                    'major_unit'       => 'Dollar',
                ],
                'HNL' => [
                    'code'         => 'HNL',
                    'symbol'       => 'HNL',
                    'name'         => 'Honduran Lempira',
                    'numeric_code' => '340',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Lempiras',
                ],
                'HRK' => [
                    'code'         => 'HRK',
                    'symbol'       => 'kn',
                    'name'         => 'Croatian Kuna',
                    'numeric_code' => '191',
                    'minor_unit'   => 'Lipa',
                    'major_unit'   => 'Kuna',
                ],
                'HTG' => [
                    'code'         => 'HTG',
                    'symbol'       => 'HTG',
                    'name'         => 'Haitian Gourde',
                    'numeric_code' => '332',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Gourde',
                ],
                'HUF' => [
                    'code'                => 'HUF',
                    'symbol'              => 'Ft',
                    'name'                => 'Hungarian Forint',
                    'numeric_code'        => '348',
                    'decimal_separator'   => ',',
                    'thousands_separator' => ' ',
                    'decimals'            => 0,
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'major_unit'          => 'Forint',
                ],
                'IDR' => [
                    'code'         => 'IDR',
                    'symbol'       => 'Rp',
                    'name'         => 'Indonesian Rupiah',
                    'decimals'     => 0,
                    'numeric_code' => '360',
                    'minor_unit'   => 'Sen',
                    'major_unit'   => 'Rupiahs',
                ],
                'ILS' => [
                    'code'             => 'ILS',
                    'symbol'           => '₪',
                    'name'             => 'Israeli New Shekel',
                    'numeric_code'     => '376',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Agora',
                    'major_unit'       => 'New Shekels',
                ],
                'INR' => [
                    'code'         => 'INR',
                    'symbol'       => 'Rs',
                    'name'         => 'Indian Rupee',
                    'numeric_code' => '356',
                    'minor_unit'   => 'Paisa',
                    'major_unit'   => 'Rupee',
                ],
                'IRR' => [
                    'code'             => 'IRR',
                    'symbol'           => '﷼',
                    'name'             => 'Iranian Rial',
                    'numeric_code'     => '364',
                    'symbol_placement' => 'after',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Rial',
                    'major_unit'       => 'Toman',
                ],
                'ISK' => [
                    'code'                => 'ISK',
                    'symbol'              => 'Ikr',
                    'name'                => 'Icelandic Króna',
                    'decimals'            => 0,
                    'thousands_separator' => ' ',
                    'numeric_code'        => '352',
                    'minor_unit'          => 'Eyrir',
                    'major_unit'          => 'Kronur',
                ],
                'JMD' => [
                    'code'             => 'JMD',
                    'symbol'           => 'J$',
                    'name'             => 'Jamaican Dollar',
                    'numeric_code'     => '388',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Cent',
                    'major_unit'       => 'Dollar',
                ],
                'JOD' => [
                    'code'         => 'JOD',
                    'symbol'       => 'JD',
                    'name'         => 'Jordanian Dinar',
                    'decimals'     => 3,
                    'numeric_code' => '400',
                    'minor_unit'   => 'Piastr',
                    'major_unit'   => 'Dinar',
                ],
                'JPY' => [
                    'code'             => 'JPY',
                    'symbol'           => '¥',
                    'name'             => 'Japanese Yen',
                    'decimals'         => 0,
                    'numeric_code'     => '392',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Sen',
                    'major_unit'       => 'Yen',
                ],
                'KES' => [
                    'code'         => 'KES',
                    'symbol'       => 'Ksh',
                    'name'         => 'Kenyan Shilling',
                    'numeric_code' => '404',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Shilling',
                ],
                'KGS' => [
                    'code'                => 'KGS',
                    'code_placement'      => 'hidden',
                    'symbol'              => 'сом',
                    'symbol_placement'    => 'after',
                    'name'                => 'Kyrgyzstani Som',
                    'numeric_code'        => '417',
                    'thousands_separator' => '',
                    'major_unit'          => 'Som',
                    'minor_unit'          => 'Tyiyn',
                ],
                'KMF' => [
                    'code'         => 'KMF',
                    'symbol'       => 'CF',
                    'name'         => 'Comorian Franc',
                    'decimals'     => 0,
                    'numeric_code' => '174',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'KRW' => [
                    'code'         => 'KRW',
                    'symbol'       => '₩',
                    'name'         => 'South Korean Won',
                    'decimals'     => 0,
                    'numeric_code' => '410',
                    'minor_unit'   => 'Jeon',
                    'major_unit'   => 'Won',
                ],
                'KWD' => [
                    'code'         => 'KWD',
                    'symbol'       => 'KD',
                    'name'         => 'Kuwaiti Dinar',
                    'decimals'     => 3,
                    'numeric_code' => '414',
                    'minor_unit'   => 'Fils',
                    'major_unit'   => 'Dinar',
                ],
                'KYD' => [
                    'code'         => 'KYD',
                    'symbol'       => 'KY$',
                    'name'         => 'Cayman Islands Dollar',
                    'numeric_code' => '136',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'KZT' => [
                    'code'                => 'KZT',
                    'symbol'              => 'тг.',
                    'name'                => 'Kazakhstani tenge',
                    'numeric_code'        => '398',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Tiyn',
                    'major_unit'          => 'Tenge',
                ],
                'LAK' => [
                    'code'         => 'LAK',
                    'symbol'       => '₭N',
                    'name'         => 'Laotian Kip',
                    'decimals'     => 0,
                    'numeric_code' => '418',
                    'minor_unit'   => 'Att',
                    'major_unit'   => 'Kips',
                ],
                'LBP' => [
                    'code'         => 'LBP',
                    'symbol'       => 'LB£',
                    'name'         => 'Lebanese Pound',
                    'decimals'     => 0,
                    'numeric_code' => '422',
                    'minor_unit'   => 'Piastre',
                    'major_unit'   => 'Pound',
                ],
                'LKR' => [
                    'code'         => 'LKR',
                    'symbol'       => 'SLRs',
                    'name'         => 'Sri Lanka Rupee',
                    'numeric_code' => '144',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Rupee',
                ],
                'LRD' => [
                    'code'         => 'LRD',
                    'symbol'       => 'L$',
                    'name'         => 'Liberian Dollar',
                    'numeric_code' => '430',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'LSL' => [
                    'code'         => 'LSL',
                    'symbol'       => 'LSL',
                    'name'         => 'Lesotho Loti',
                    'numeric_code' => '426',
                    'minor_unit'   => 'Sente',
                    'major_unit'   => 'Loti',
                ],
                'LTL' => [
                    'code'         => 'LTL',
                    'symbol'       => 'Lt',
                    'name'         => 'Lithuanian Litas',
                    'numeric_code' => '440',
                    'minor_unit'   => 'Centas',
                    'major_unit'   => 'Litai',
                ],
                'LVL' => [
                    'code'         => 'LVL',
                    'symbol'       => 'Ls',
                    'name'         => 'Latvian Lats',
                    'numeric_code' => '428',
                    'minor_unit'   => 'Santims',
                    'major_unit'   => 'Lati',
                ],
                'LYD' => [
                    'code'         => 'LYD',
                    'symbol'       => 'LD',
                    'name'         => 'Libyan Dinar',
                    'decimals'     => 3,
                    'numeric_code' => '434',
                    'minor_unit'   => 'Dirham',
                    'major_unit'   => 'Dinar',
                ],
                'MAD' => [
                    'code'             => 'MAD',
                    'symbol'           => ' Dhs',
                    'name'             => 'Moroccan Dirham',
                    'numeric_code'     => '504',
                    'symbol_placement' => 'after',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Santimat',
                    'major_unit'       => 'Dirhams',
                ],
                'MDL' => [
                    'code'             => 'MDL',
                    'symbol'           => 'MDL',
                    'name'             => 'Moldovan leu',
                    'symbol_placement' => 'after',
                    'numeric_code'     => '498',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'bani',
                    'major_unit'       => 'Lei',
                ],
                'MMK' => [
                    'code'         => 'MMK',
                    'symbol'       => 'MMK',
                    'name'         => 'Myanma Kyat',
                    'decimals'     => 0,
                    'numeric_code' => '104',
                    'minor_unit'   => 'Pya',
                    'major_unit'   => 'Kyat',
                ],
                'MNT' => [
                    'code'         => 'MNT',
                    'symbol'       => '₮',
                    'name'         => 'Mongolian Tugrik',
                    'decimals'     => 0,
                    'numeric_code' => '496',
                    'minor_unit'   => 'Möngö',
                    'major_unit'   => 'Tugriks',
                ],
                'MOP' => [
                    'code'         => 'MOP',
                    'symbol'       => 'MOP$',
                    'name'         => 'Macanese Pataca',
                    'numeric_code' => '446',
                    'minor_unit'   => 'Avo',
                    'major_unit'   => 'Pataca',
                ],
                'MRO' => [
                    'code'         => 'MRO',
                    'symbol'       => 'UM',
                    'name'         => 'Mauritanian Ouguiya',
                    'decimals'     => 0,
                    'numeric_code' => '478',
                    'minor_unit'   => 'Khoums',
                    'major_unit'   => 'Ouguiya',
                ],
                'MTP' => [
                    'code'       => 'MTP',
                    'symbol'     => 'MT£',
                    'name'       => 'Maltese Pound',
                    'minor_unit' => 'Shilling',
                    'major_unit' => 'Pound',
                ],
                'MUR' => [
                    'code'         => 'MUR',
                    'symbol'       => 'MURs',
                    'name'         => 'Mauritian Rupee',
                    'decimals'     => 0,
                    'numeric_code' => '480',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Rupee',
                ],
                'MXN' => [
                    'code'             => 'MXN',
                    'symbol'           => '$',
                    'name'             => 'Mexican Peso',
                    'numeric_code'     => '484',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Centavo',
                    'major_unit'       => 'Peso',
                ],
                'MYR' => [
                    'code'             => 'MYR',
                    'symbol'           => 'RM',
                    'name'             => 'Malaysian Ringgit',
                    'numeric_code'     => '458',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Sen',
                    'major_unit'       => 'Ringgits',
                ],
                'MZN' => [
                    'code'       => 'MZN',
                    'symbol'     => 'MTn',
                    'name'       => 'Mozambican Metical',
                    'minor_unit' => 'Centavo',
                    'major_unit' => 'Metical',
                ],
                'NAD' => [
                    'code'         => 'NAD',
                    'symbol'       => 'N$',
                    'name'         => 'Namibian Dollar',
                    'numeric_code' => '516',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'NGN' => [
                    'code'         => 'NGN',
                    'symbol'       => '₦',
                    'name'         => 'Nigerian Naira',
                    'numeric_code' => '566',
                    'minor_unit'   => 'Kobo',
                    'major_unit'   => 'Naira',
                ],
                'NIO' => [
                    'code'         => 'NIO',
                    'symbol'       => 'C$',
                    'name'         => 'Nicaraguan Cordoba Oro',
                    'numeric_code' => '558',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Cordoba',
                ],
                'NOK' => [
                    'code'                => 'NOK',
                    'symbol'              => 'Nkr',
                    'name'                => 'Norwegian Krone',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'numeric_code'        => '578',
                    'minor_unit'          => 'Øre',
                    'major_unit'          => 'Krone',
                ],
                'NPR' => [
                    'code'         => 'NPR',
                    'symbol'       => 'NPRs',
                    'name'         => 'Nepalese Rupee',
                    'numeric_code' => '524',
                    'minor_unit'   => 'Paisa',
                    'major_unit'   => 'Rupee',
                ],
                'NZD' => [
                    'code'         => 'NZD',
                    'symbol'       => 'NZ$',
                    'name'         => 'New Zealand Dollar',
                    'numeric_code' => '554',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'OMR' => [
                    'code'         => 'OMR',
                    'symbol'       => 'OMR',
                    'name'         => 'Omani Rial',
                    'numeric_code' => '512',
                    'minor_unit'   => 'Baisa',
                    'major_unit'   => 'Rials',
                ],
                'PAB' => [
                    'code'         => 'PAB',
                    'symbol'       => 'B/.',
                    'name'         => 'Panamanian Balboa',
                    'numeric_code' => '590',
                    'minor_unit'   => 'Centésimo',
                    'major_unit'   => 'Balboa',
                ],
                'PEN' => [
                    'code'             => 'PEN',
                    'symbol'           => 'S/.',
                    'name'             => 'Peruvian Nuevo Sol',
                    'numeric_code'     => '604',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Céntimo',
                    'major_unit'       => 'Nuevos Sole',
                ],
                'PGK' => [
                    'code'         => 'PGK',
                    'symbol'       => 'PGK',
                    'name'         => 'Papua New Guinean Kina',
                    'numeric_code' => '598',
                    'minor_unit'   => 'Toea',
                    'major_unit'   => 'Kina ',
                ],
                'PHP' => [
                    'code'         => 'PHP',
                    'symbol'       => '₱',
                    'name'         => 'Philippine Peso',
                    'numeric_code' => '608',
                    'minor_unit'   => 'Centavo',
                    'major_unit'   => 'Peso',
                ],
                'PKR' => [
                    'code'         => 'PKR',
                    'symbol'       => 'PKRs',
                    'name'         => 'Pakistani Rupee',
                    'decimals'     => 0,
                    'numeric_code' => '586',
                    'minor_unit'   => 'Paisa',
                    'major_unit'   => 'Rupee',
                ],
                'PLN' => [
                    'code'                => 'PLN',
                    'symbol'              => 'zł',
                    'name'                => 'Polish Złoty',
                    'decimal_separator'   => ',',
                    'thousands_separator' => ' ',
                    'numeric_code'        => '985',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Grosz',
                    'major_unit'          => 'Złotych',
                ],
                'PYG' => [
                    'code'         => 'PYG',
                    'symbol'       => '₲',
                    'name'         => 'Paraguayan Guarani',
                    'decimals'     => 0,
                    'numeric_code' => '600',
                    'minor_unit'   => 'Céntimo',
                    'major_unit'   => 'Guarani',
                ],
                'QAR' => [
                    'code'         => 'QAR',
                    'symbol'       => 'QR',
                    'name'         => 'Qatari Rial',
                    'numeric_code' => '634',
                    'minor_unit'   => 'Dirham',
                    'major_unit'   => 'Rial',
                ],
                'RHD' => [
                    'code'       => 'RHD',
                    'symbol'     => 'RH$',
                    'name'       => 'Rhodesian Dollar',
                    'minor_unit' => 'Cent',
                    'major_unit' => 'Dollar',
                ],
                'RON' => [
                    'code'       => 'RON',
                    'symbol'     => 'RON',
                    'name'       => 'Romanian Leu',
                    'minor_unit' => 'Ban',
                    'major_unit' => 'Leu',
                ],
                'RSD' => [
                    'code'       => 'RSD',
                    'symbol'     => 'din.',
                    'name'       => 'Serbian Dinar',
                    'decimals'   => 0,
                    'minor_unit' => 'Para',
                    'major_unit' => 'Dinars',
                ],
                'RUB' => [
                    'code'                => 'RUB',
                    'symbol'              => 'руб.',
                    'name'                => 'Russian Ruble',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'numeric_code'        => '643',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Kopek',
                    'major_unit'          => 'Ruble',
                ],
                'SAR' => [
                    'code'         => 'SAR',
                    'symbol'       => 'SR',
                    'name'         => 'Saudi Riyal',
                    'numeric_code' => '682',
                    'minor_unit'   => 'Hallallah',
                    'major_unit'   => 'Riyals',
                ],
                'SBD' => [
                    'code'         => 'SBD',
                    'symbol'       => 'SI$',
                    'name'         => 'Solomon Islands Dollar',
                    'numeric_code' => '090',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'SCR' => [
                    'code'         => 'SCR',
                    'symbol'       => 'SRe',
                    'name'         => 'Seychellois Rupee',
                    'numeric_code' => '690',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Rupee',
                ],
                'SDD' => [
                    'code'         => 'SDD',
                    'symbol'       => 'LSd',
                    'name'         => 'Old Sudanese Dinar',
                    'numeric_code' => '736',
                    'minor_unit'   => 'None',
                    'major_unit'   => 'Dinar',
                ],
                'SEK' => [
                    'code'                => 'SEK',
                    'symbol'              => 'kr',
                    'name'                => 'Swedish Krona',
                    'numeric_code'        => '752',
                    'thousands_separator' => ' ',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Öre',
                    'major_unit'          => 'Kronor',
                ],
                'SGD' => [
                    'code'         => 'SGD',
                    'symbol'       => 'S$',
                    'name'         => 'Singapore Dollar',
                    'numeric_code' => '702',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'SHP' => [
                    'code'         => 'SHP',
                    'symbol'       => 'SH£',
                    'name'         => 'Saint Helena Pound',
                    'numeric_code' => '654',
                    'minor_unit'   => 'Penny',
                    'major_unit'   => 'Pound',
                ],
                'SLL' => [
                    'code'         => 'SLL',
                    'symbol'       => 'Le',
                    'name'         => 'Sierra Leonean Leone',
                    'decimals'     => 0,
                    'numeric_code' => '694',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Leone',
                ],
                'SOS' => [
                    'code'         => 'SOS',
                    'symbol'       => 'Ssh',
                    'name'         => 'Somali Shilling',
                    'decimals'     => 0,
                    'numeric_code' => '706',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Shilling',
                ],
                'SRD' => [
                    'code'       => 'SRD',
                    'symbol'     => 'SR$',
                    'name'       => 'Surinamese Dollar',
                    'minor_unit' => 'Cent',
                    'major_unit' => 'Dollar',
                ],
                'SRG' => [
                    'code'         => 'SRG',
                    'symbol'       => 'Sf',
                    'name'         => 'Suriname Guilder',
                    'numeric_code' => '740',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Guilder',
                ],
                'STD' => [
                    'code'         => 'STD',
                    'symbol'       => 'Db',
                    'name'         => 'São Tomé and Príncipe Dobra',
                    'decimals'     => 0,
                    'numeric_code' => '678',
                    'minor_unit'   => 'Cêntimo',
                    'major_unit'   => 'Dobra',
                ],
                'SYP' => [
                    'code'         => 'SYP',
                    'symbol'       => 'SY£',
                    'name'         => 'Syrian Pound',
                    'decimals'     => 0,
                    'numeric_code' => '760',
                    'minor_unit'   => 'Piastre',
                    'major_unit'   => 'Pound',
                ],
                'SZL' => [
                    'code'         => 'SZL',
                    'symbol'       => 'SZL',
                    'name'         => 'Swazi Lilangeni',
                    'numeric_code' => '748',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Lilangeni',
                ],
                'THB' => [
                    'code'         => 'THB',
                    'symbol'       => '฿',
                    'name'         => 'Thai Baht',
                    'numeric_code' => '764',
                    'minor_unit'   => 'Satang',
                    'major_unit'   => 'Baht',
                ],
                'TND' => [
                    'code'         => 'TND',
                    'symbol'       => 'DT',
                    'name'         => 'Tunisian Dinar',
                    'decimals'     => 3,
                    'numeric_code' => '788',
                    'minor_unit'   => 'Millime',
                    'major_unit'   => 'Dinar',
                ],
                'TOP' => [
                    'code'         => 'TOP',
                    'symbol'       => 'T$',
                    'name'         => 'Tongan Paʻanga',
                    'numeric_code' => '776',
                    'minor_unit'   => 'Senit',
                    'major_unit'   => 'Paʻanga',
                ],
                'TRY' => [
                    'code'                => 'TRY',
                    'symbol'              => 'TL',
                    'name'                => 'Turkish Lira',
                    'numeric_code'        => '949',
                    'thousands_separator' => '.',
                    'decimal_separator'   => ',',
                    'symbol_placement'    => 'after',
                    'code_placement'      => '',
                    'minor_unit'          => 'Kurus',
                    'major_unit'          => 'Lira',
                ],
                'TTD' => [
                    'code'         => 'TTD',
                    'symbol'       => 'TT$',
                    'name'         => 'Trinidad and Tobago Dollar',
                    'numeric_code' => '780',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'TWD' => [
                    'code'         => 'TWD',
                    'symbol'       => 'NT$',
                    'name'         => 'New Taiwan Dollar',
                    'numeric_code' => '901',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'New Dollar',
                ],
                'TZS' => [
                    'code'         => 'TZS',
                    'symbol'       => 'TSh',
                    'name'         => 'Tanzanian Shilling',
                    'decimals'     => 0,
                    'numeric_code' => '834',
                    'minor_unit'   => 'Senti',
                    'major_unit'   => 'Shilling',
                ],
                'UAH' => [
                    'code'                => 'UAH',
                    'symbol'              => 'грн.',
                    'name'                => 'Ukrainian Hryvnia',
                    'numeric_code'        => '980',
                    'thousands_separator' => '',
                    'decimal_separator'   => '.',
                    'symbol_placement'    => 'after',
                    'code_placement'      => 'hidden',
                    'minor_unit'          => 'Kopiyka',
                    'major_unit'          => 'Hryvnia',
                ],
                'UGX' => [
                    'code'         => 'UGX',
                    'symbol'       => 'USh',
                    'name'         => 'Ugandan Shilling',
                    'decimals'     => 0,
                    'numeric_code' => '800',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Shilling',
                ],
                'USD' => [
                    'code'             => 'USD',
                    'symbol'           => '$',
                    'name'             => 'United States Dollar',
                    'numeric_code'     => '840',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Cent',
                    'major_unit'       => 'Dollar',
                ],
                'UYU' => [
                    'code'         => 'UYU',
                    'symbol'       => '$U',
                    'name'         => 'Uruguayan Peso',
                    'numeric_code' => '858',
                    'minor_unit'   => 'Centésimo',
                    'major_unit'   => 'Peso',
                ],
                'VEF' => [
                    'code'       => 'VEF',
                    'symbol'     => 'Bs.F.',
                    'name'       => 'Venezuelan Bolívar Fuerte',
                    'minor_unit' => 'Céntimo',
                    'major_unit' => 'Bolivares Fuerte',
                ],
                'VND' => [
                    'code'                => 'VND',
                    'symbol'              => 'đ',
                    'name'                => 'Vietnamese Dong',
                    'decimals'            => 0,
                    'thousands_separator' => '.',
                    'symbol_placement'    => 'after',
                    'symbol_spacer'       => '',
                    'code_placement'      => 'hidden',
                    'numeric_code'        => '704',
                    'minor_unit'          => 'Hà',
                    'major_unit'          => 'Dong',
                ],
                'VUV' => [
                    'code'         => 'VUV',
                    'symbol'       => 'VT',
                    'name'         => 'Vanuatu Vatu',
                    'decimals'     => 0,
                    'numeric_code' => '548',
                    'major_unit'   => 'Vatu',
                ],
                'WST' => [
                    'code'         => 'WST',
                    'symbol'       => 'WS$',
                    'name'         => 'Samoan Tala',
                    'numeric_code' => '882',
                    'minor_unit'   => 'Sene',
                    'major_unit'   => 'Tala',
                ],
                'XAF' => [
                    'code'         => 'XAF',
                    'symbol'       => 'FCFA',
                    'name'         => 'CFA Franc BEAC',
                    'decimals'     => 0,
                    'numeric_code' => '950',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'XCD' => [
                    'code'         => 'XCD',
                    'symbol'       => 'EC$',
                    'name'         => 'East Caribbean Dollar',
                    'numeric_code' => '951',
                    'minor_unit'   => 'Cent',
                    'major_unit'   => 'Dollar',
                ],
                'XOF' => [
                    'code'         => 'XOF',
                    'symbol'       => 'CFA',
                    'name'         => 'CFA Franc BCEAO',
                    'decimals'     => 0,
                    'numeric_code' => '952',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'XPF' => [
                    'code'         => 'XPF',
                    'symbol'       => 'CFPF',
                    'name'         => 'CFP Franc',
                    'decimals'     => 0,
                    'numeric_code' => '953',
                    'minor_unit'   => 'Centime',
                    'major_unit'   => 'Franc',
                ],
                'YER' => [
                    'code'         => 'YER',
                    'symbol'       => 'YR',
                    'name'         => 'Yemeni Rial',
                    'decimals'     => 0,
                    'numeric_code' => '886',
                    'minor_unit'   => 'Fils',
                    'major_unit'   => 'Rial',
                ],
                'ZAR' => [
                    'code'             => 'ZAR',
                    'symbol'           => 'R',
                    'name'             => 'South African Rand',
                    'numeric_code'     => '710',
                    'symbol_placement' => 'before',
                    'code_placement'   => 'hidden',
                    'minor_unit'       => 'Cent',
                    'major_unit'       => 'Rand',
                ],
                'ZMK' => [
                    'code'         => 'ZMK',
                    'symbol'       => 'ZK',
                    'name'         => 'Zambian Kwacha',
                    'decimals'     => 0,
                    'numeric_code' => '894',
                    'minor_unit'   => 'Ngwee',
                    'major_unit'   => 'Kwacha',
                ],
                'MKD' => [
                    'code'       => 'MKD',
                    'symbol'     => 'ден',
                    'name'       => 'Macedonian Denar',
                    'decimals'   => 0,
                    'minor_unit' => 'Deni',
                ],
            ]
        );
    }


}