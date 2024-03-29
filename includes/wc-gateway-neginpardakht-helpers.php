<?php
/**
 * @file Contains helper functions.
 */

/**
 * Helper function to obtain the amount by considering whether a unit price is
 * in Iranian Rial Or Iranian Toman unit.
 *
 * As the NeginPardakht gateway accepts orders with IRR unit price, We must
 * convert Tomans into Rials by multiplying them by 10.
 *
 * Also there are some unofficial currency codes which are common in
 * the Iranian community and We must convert them to IRR, if anyone chooses
 * them.
 *
 * @param $amount
 * @param $currency
 *
 * @return float|int
 */
function wc_neginpardakht_get_amount( $amount, $currency ) {
	switch ( strtolower( $currency ) ) {
		case strtolower( 'IRR' ):
		case strtolower( 'RIAL' ):
			return $amount;

		case strtolower( 'تومان ایران' ):
		case strtolower( 'تومان' ):
		case strtolower( 'IRT' ):
		case strtolower( 'Iranian_TOMAN' ):
		case strtolower( 'Iran_TOMAN' ):
		case strtolower( 'Iranian-TOMAN' ):
		case strtolower( 'Iran-TOMAN' ):
		case strtolower( 'TOMAN' ):
		case strtolower( 'Iran TOMAN' ):
		case strtolower( 'Iranian TOMAN' ):
			return $amount * 10;

		case strtolower( 'IRHR' ):
			return $amount * 1000;

		case strtolower( 'IRHT' ):
			return $amount * 10000;

		default:
			return 0;
	}
}
