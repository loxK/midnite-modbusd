<?php
/**
 *  MIDNITE-CLASSIC
 *
 *  A module class to talk to the Midnite Classic charge controller via RossW's linux modbus c utility.
 *  All care no responsibility, use at your own risk.
 *
 * @revision: $Rev$
 * @author  :   Peter 2013
 * @author  :   Lauent Dinclaux <laurent@gecka.nc> 2018
 * @license :  GPLv3.
 *
 **/

namespace Gecka\Midnite;

class Classic {

    protected $registers = []; //temp store raw registers

    public function __construct() {
    }

    public function print_json( $data ) {

        $data = $this->parse_data( $data );
        echo json_encode($data, JSON_PRETTY_PRINT);
        die(0);
    }

    /**
     * DEFINE_DATAPOINTS
     * datapoint definitions
     *
     * @args nil
     * @return nil
     *
     **/

    public function datapoints() {

        $defns = [];
        $order = 1;

        //note that anytime you make changes to the registers sampled you need to run
        //the check db ui to ensure database tables are in synch


        ### THE LESS CHANGEABLE DEVICE STATS
        //these will only change occasionally, when cc is swapped, firmware upgraded etc
        //so we'll store them daily and call it good

        $defns['classic'] = [
            'name'      => "Classic Unit Type",
            'interval'  => 'day',
            'method'    => 'get_register_expr',
            'arguments' => [ 'self::LSB([4101])' ],
            'comment'   => '(int) Classic 150=150,Classic 200=200, Classic 250=250, Classic 250KS=251',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['rev'] = [
            'name'      => "Classic PCB Revision",
            'interval'  => 'day',
            'method'    => 'get_register_expr',
            'arguments' => [ 'self::MSB([4101])' ],
            'comment'   => '(int) 1-3',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['firmdate'] = [
            'name'      => "Firmware Date",
            'interval'  => 'day',
            'method'    => 'get_register_expr',
            'arguments' => [ "'[4102]'.'-'.\Gecka\Midnite\Classic::MSB([4103]).'-'.\Gecka\Midnite\Classic::LSB([4103])" ],
            'comment'   => '(isodate) year-month-day',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['firmver'] = [
            'name'      => "Firmware Version",
            'interval'  => 'day',
            'method'    => 'get_register_expr',
            'arguments' => "[16387]",    // "BITS([16385],15,12).'.'.BITS([16385],11,8).'.'.BITS([16385],7,4)",
            'comment'   => '',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['commver'] = [
            'name'      => "Revision of the communications code stack",
            'interval'  => 'day',
            'method'    => 'get_register_expr',
            'arguments' => "[16389]",
            'comment'   => '',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['uptimeh'] = [
            'name'      => "Uptime",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => 'round((([4350]<< 16) + [4349]))',
            'comment'   => '(decimal) uptime in seconds',
            'unit'      => 'seconds',
            'order'     => $order ++,
        ];

        $defns['uptimeh'] = [
            'name'      => "Uptime hours",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => 'round((([4350]<< 16) + [4349])/60/60)',
            'comment'   => '(decimal) uptime in hours, 2dp',
            'unit'      => 'hours',
            'order'     => $order ++,
        ];

        $defns['uptime'] = [
            'name'      => "Uptime days",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => 'round((([4350]<< 16) + [4349])/60/60/24,2)',
            'comment'   => '(decimal) uptime in days, 2dp',
            'unit'      => 'days',
            'priority'  => 3,
            'order'     => $order ++,
        ];

        $defns['plifetime'] = [
            'name'      => "Lifetime kWh",
            'interval'  => 'day',
            'method'    => 'get_register_expr',
            'arguments' => '(([4127] << 16) + [4126])/ 10',
            'comment'   => '(decimal) kilowatt hours since new',
            'unit'      => 'kWh',
            'order'     => $order ++,
        ];

        $defns['ptoday'] = [
            'name'      => "kWh Today",
            'interval'  => 'day',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4118, 1 ],
            'comment'   => 'Average Energy to the Battery This is reset once per day',
            'unit'      => 'kWh',
            'order'     => $order ++,
        ];

        $defns['ftoday'] = [
            'name'      => "Float Time Today",
            'interval'  => 'day',
            'method'    => 'get_register',
            'arguments' => 4138,
            'comment'   => '(decimal) register seconds',
            'unit'      => 'seconds',
            'order'     => $order ++,
        ];

        //CHARGE STAGE
        //the register is an integer, but not quite in linear order
        //hence we have 2 derived versions, one in english and one in linear order
        //Raw: 0=Resting,3=Absorb,4=BulkMppt,5=Float,6=FloatMppt,7=Equalize,10=HyperVoc,18=EqMppt

        $defns['cstate'] = [
            'name'      => "Charge Stage Raw",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => 'self::MSB([4120])',
            'comment'   => '(int)',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['cstageword'] = [
            'name'      => "Charge Stage",
            'interval'  => 'periodic',
            'method'    => 'translate_stage',
            'arguments' => 'word',
            'comment'   => '(string) in english',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['cstagelin'] = [
            'name'      => "Charge Stage Lin",
            'interval'  => 'periodic',
            'method'    => 'translate_stage',
            'arguments' => 'linear',
            'comment'   => '(int) in sequence',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['state'] = [
            'name'      => "State Raw",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => 'self::LSB([4120])',
            'comment'   => '(int)',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['stateword'] = [
            'name'     => "State",
            'interval' => 'periodic',
            'method'   => 'translate_state',
            'comment'  => '(int)',
            'unit'     => '',
            'order'    => $order ++,
        ];

        $defns['restingreason'] = [
            'name'      => "Reason for resting",
            'interval'  => 'periodic',
            'method'    => 'get_register',
            'arguments' => '4275',
            'comment'   => '(int)',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['restingreasonword'] = [
            'name'     => "Reason for resting",
            'interval' => 'periodic',
            'method'   => 'translate_resting',
            'comment'  => '(int)',
            'unit'     => '',
            'order'    => $order ++,
        ];

        $defns['restingreasonwordshort'] = [
            'name'     => "Reason for resting",
            'interval' => 'periodic',
            'method'   => 'translate_resting_short',
            'comment'  => '(int)',
            'unit'     => '',
            'order'    => $order ++,
        ];

        //FLAGS
        $defns['infoflags'] = [
            'name'      => "Info Flags",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => '(([4131] << 16) + [4130])',
            'comment'   => '(int) decimal rendition of hex flags',
            'unit'      => '',
            'order'     => $order ++,
        ];

        /*
        //temp for netowrk fix
        $defns['debug5'] = array(
            'name'     => "Debug5",
            'interval' => 'periodic',
            'method'   => 'get_register_expr',
            'arguments' => '(([4387] << 16) + [4386])',
            'comment'  => '(int) decimal rendition of hex flags',
            'unit'     => '',
            'order'    => $order ++,
        );
        */

        //TEMPS
        //the most interesting of which is tbat
        //also present are tfet and tpcb
        //we will assume that tfet is a good enough proxy for cc temp

        $defns['tbat'] = [
            'name'      => "Battery Temp",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => '[4132] > 65000 ? 25 : [4132]/10',
            'comment'   => '(decimal)',
            'unit'      => '°C',
            'priority'  => 2,
            'order'     => $order ++,
        ];

        $defns['tcc'] = [
            'name'      => "FET Temp",
            'type'      => 'sampled',
            'store'     => TRUE,
            'interval'  => 'periodic',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4133, 1 ],
            'comment'   => '(decimal) fet',
            'unit'      => '°C',
            'priority'  => 2,
            'order'     => $order ++,
        ];

        $defns['tcc2'] = [
            'name'      => "PCB Temp",
            'interval'  => 'periodic',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4134, 1 ],
            'comment'   => '(decimal) pcb',
            'unit'      => '°C',
            'order'     => $order ++,
        ];

        //VOLTS AND AMPS
        //pv volts and amps, battery volts and amps, and pout
        //theres one level of redundancy there, and, problematically they dont agree
        //but for now we will store them all, until someone can shed some light on this
        //just for kicks well track the pin/pout efficiency
        $defns['pout'] = [
            'name'      => "Output Power",
            'interval'  => 'periodic',
            'method'    => 'get_register',
            'arguments' => 4119,
            'comment'   => '(int)',
            'unit'      => 'W',
            'order'     => $order ++,
        ];

        $defns['vout'] = [
            'name'      => "Output Voltage",
            'interval'  => 'periodic',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4115, 1 ],
            'comment'   => '(decimal) cc output voltage at controller',
            'unit'      => 'V',
            'order'     => $order ++,
        ];

        $defns['iout'] = [
            'name'      => "Output Current",
            'type'      => 'sampled',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4117, 1 ],
            'comment'   => '(decimal) cc output current',
            'unit'      => 'A',
            'order'     => $order ++,
        ];

        //pv array figures
        $defns['vpv'] = [
            'name'      => "PV Voltage",
            'type'      => 'sampled',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4116, 1 ],
            'comment'   => '(decimal)',
            'unit'      => 'V',
            'priority'  => 2,
            'order'     => $order ++,
        ];

        $defns['ipv'] = [
            'name'      => "PV Current",
            'type'      => 'sampled',
            'method'    => 'get_register10_flt',
            'arguments' => [ 4121, 1 ],
            'comment'   => '(decimal)',
            'unit'      => 'A',
            'priority'  => 2,
            'order'     => $order ++,
        ];

        //whizbang figures
        $defns['whizbtemp'] = [
            'name'      => "WhizBangJr Shunt Temperature",
            'interval'  => 'periodic',
            'method'    => 'get_register_expr',
            'arguments' => 'self::LSB([4372])-50',
            'comment'   => '(decimal)',
            'unit'      => '°C',
            'order'     => $order ++,
        ];

        $defns['ibat'] = [
            'name'      => "WhizBangJr Current",
            'interval'  => 'periodic',
            'method'    => 'get_register10_flt_signed',
            'arguments' => [ 4371, 1 ],
            'comment'   => '(decimal) +/- battery current, 1dp',
            'unit'      => 'A',
            'order'     => $order ++,
        ];

        $defns['soc'] = [
            'name'      => "State of Charge",
            'interval'  => 'periodic',
            'method'    => 'get_register',
            'arguments' => 4373,
            'comment'   => '(int) battery SOC (0dp)',
            'unit'      => '%',
            'order'     => $order ++,
        ];

        $defns['battahrem'] = [
            'name'      => "Remaining battery capacity",
            'interval'  => 'periodic',
            'method'    => 'get_register',
            'arguments' => 4377,
            'comment'   => '(int)',
            'unit'      => 'Ah',
            'order'     => $order ++,
        ];

        $defns['battah'] = [
            'name'      => "Battery capacity",
            'interval'  => 'periodic',
            'method'    => 'get_register',
            'arguments' => 4381,
            'comment'   => '(int)',
            'unit'      => 'Ah',
            'order'     => $order ++,
        ];

        $defns['iabsbat'] = [
            'name'      => "Battery Current Abs",
            'interval'  => 'periodic',
            'method'    => 'calc_load_data',
            'arguments' => 'iabsbat',
            'comment'   => '(decimal) signless battery current',
            'unit'      => 'A',
            'order'     => $order ++,
        ];
        $defns['ichgbat'] = [
            'name'      => "Battery Current Charge",
            'interval'  => 'periodic',
            'method'    => 'calc_load_data',
            'arguments' => 'ichgbat',
            'comment'   => '(decimal) signless battery current',
            'unit'      => 'A',
            'order'     => $order ++,
        ];
        $defns['idisbat'] = [
            'name'      => "Battery Current Discharge",
            'interval'  => 'periodic',
            'method'    => 'calc_load_data',
            'arguments' => 'idisbat',
            'comment'   => '(decimal) signless battery current',
            'unit'      => 'A',
            'order'     => $order ++,
        ];

        $defns['batstate'] = [
            'name'      => "Battery Current State",
            'interval'  => 'periodic',
            'method'    => 'calc_load_data',
            'arguments' => 'batstate',
            'comment'   => '(string) Charging/Discharging',
            'unit'      => '',
            'order'     => $order ++,
        ];

        $defns['iload'] = [
            'name'      => "Load Current",
            'interval'  => 'periodic',
            'method'    => 'calc_load_data',
            'arguments' => 'iload',
            'comment'   => '(decimal) load current, 1dp',
            'unit'      => 'A',
            'order'     => $order ++,
        ];

        $defns['pload'] = [
            'name'      => "Load Power",
            'interval'  => 'periodic',
            'method'    => 'calc_load_data',
            'arguments' => 'pload',
            'comment'   => '(int)',
            'unit'      => 'W',
            'order'     => $order ++,
        ];


        $defns['eff'] = [
            'name'     => "Efficiency",
            'interval' => 'periodic',
            'method'   => 'calc_efficiency',
            'comment'  => '(decimal) pin cf pout',
            'unit'     => '%',
            'order'    => $order ++,
        ];

        /*
                                #### REALTIME STATS OF INTEREST


                                //DAY TO DATE
                                //the classic tracks float time, and energy today
                                //note that ftoday and ptoday will be garbage if the classic clock is wrong
                                //we will add absorb time, bulk time, our own float time
                                //we will also derive kWh in all three states.
                                //for our derived versions well use the prefix dur for duration

                                $defns['lastfloat'] = array(
                                    'name'     => "Days Since Float",
                                    'type'     => 'derived',
                                    'store'    => false,
                                    'interval' => 'day',
                                    'method'   => 'calc_days_since',
                                    'argument' => 'float',
                                    'comment'  => '(int) days',
                                    'unit'     => 'days',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );
                                $defns['durbulk']   = array(
                                    'name'     => "Time in bulk",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_duration',
                                    'argument' => 'bulk',
                                    'comment'  => '(decimal) hours',
                                    'unit'     => 'hrs',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['durabsorb'] = array(
                                    'name'     => "Time in absorb",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_duration',
                                    'argument' => 'absorb',
                                    'comment'  => '(decimal) hours',
                                    'unit'     => 'hrs',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['durfloat'] = array(
                                    'name'     => "Time in float",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_duration',
                                    'argument' => 'float',
                                    'comment'  => '(decimal) hours, computed',
                                    'unit'     => 'hrs',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['whtotal'] = array(
                                    'name'     => "Wh total",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_sum',
                                    'argument' => 'pout/total',
                                    'comment'  => '(decimal) ',
                                    'unit'     => 'Wh',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );
                                $defns['whbulk']  = array(
                                    'name'     => "Wh in bulk",
                                    'type'     => 'derived',
                                    'store'    => false,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_sum',
                                    'argument' => 'pout/bulk',
                                    'comment'  => '(decimal) ',
                                    'unit'     => 'Wh',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['whabsorb'] = array(
                                    'name'     => "Wh in absorb",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_sum',
                                    'argument' => 'pout/absorb',
                                    'comment'  => '(decimal) ',
                                    'unit'     => 'Wh',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['whfloat'] = array(
                                    'name'     => "Wh in float",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_sum',
                                    'argument' => 'pout/float',
                                    'comment'  => '(decimal) ',
                                    'unit'     => 'Wh',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                //WBjr dailys
                                $defns['whload'] = array(
                                    'name'     => "Wh Load",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_wbjr_deriv',
                                    'argument' => 'whload',
                                    'comment'  => '(decimal) load power, 0dp',
                                    'unit'     => 'Wh',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['ahcharge'] = array(
                                    'name'     => "Charge Amp Hrs",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_wbjr_deriv',
                                    'argument' => 'ahcharge',
                                    'comment'  => '(decimal) amp hours into battery today, 1dp',
                                    'unit'     => 'Ah',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['ahdischarge'] = array(
                                    'name'     => "Discharge  Amp Hrs",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_wbjr_deriv',
                                    'argument' => 'ahdischarge',
                                    'comment'  => '(decimal) amp hours out of battery today, 1dp',
                                    'unit'     => 'Ah',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                //and the classics net amp hour counter.
                                //its not really cear yet how this works, assuming it resets upon float
                                //(4365,4366)  WbJr.  unsigned 32 bits Amp-Hours Positive Only  Low,High
                                //(4367,4368)  WbJr.  signed 32 bits Amp-Hours Negative Only   Low,High
                                //(4369 4370)  WbJr.  signed 32 bits Amp-Hours Positive AND Negative    Low,High
                                //  '(([4370] << 16) + [4369])',
                                //new localapp says net = -21 Ah
                                //classic reads
                                //4365 574  =dec 574
                                //4366 0
                                //4367 64941 =dec 595
                                //4368 65535
                                //4369 65515 =dec 21
                                //4370 65535 =an entire byte for just the sign?
                                //...then later net = 1 Ah
                                //4369 1
                                //4370 0


                                //			'argument'=>   '\Gecka\Midnite\Classic::BITS([4371],15) ? (65536-[4371])/10 : [4371]/-10',


                                $defns['ahnet'] = array(
                                    'name'     => "Whizbang Net Ah",
                                    'type'     => 'sampled',
                                    'store'    => true,
                                    'interval' => 'periodic',
                                    'method'   => 'get_register',
                                    'argument' => '\Gecka\Midnite\Classic::BITS([4369],15) ? -(65536-[4369]) : [4369]',
                                    'comment'  => '(decimal) 0dp',
                                    'unit'     => 'Ah',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );


                                //DAILY PEAKS AND DIPS
                                //primarily we are interested in peak pout, peak iout, vbat high and low.
                                //but im sure others will surface

                                $defns['maxpout'] = array(
                                    'name'     => "Max power output",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_max',
                                    'argument' => 'pout',
                                    'comment'  => '(decimal)',
                                    'unit'     => 'W',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['maxiout'] = array(
                                    'name'     => "Max current output",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_max',
                                    'argument' => 'iout',
                                    'comment'  => '(decimal)',
                                    'unit'     => 'A',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['maxvbat'] = array(
                                    'name'     => "Max battery voltage",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_max',
                                    'argument' => 'vout',
                                    'comment'  => '(decimal)',
                                    'unit'     => 'V',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );

                                $defns['minvbat']  = array(
                                    'name'     => "Min battery voltage",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_min',
                                    'argument' => 'vout',
                                    'comment'  => '(decimal)',
                                    'unit'     => 'V',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );
                                $defns['restvbat'] = array(
                                    'name'     => "Rest battery voltage",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_rest_voltage',
                                    'argument' => '05:00',
                                    // time of day consistently prior to day time loads kicking in, and before sun comes up
                                    'comment'  => '(decimal) highest vbat between 0430 and 0500',
                                    'unit'     => 'V',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );
                                $defns['minsoc']   = array(
                                    'name'     => "Min SOC",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_min',
                                    'argument' => 'soc',
                                    'comment'  => '(decimal)',
                                    'unit'     => '%',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );
                                $defns['maxsoc']   = array(
                                    'name'     => "Max SOC",
                                    'type'     => 'derived',
                                    'store'    => true,
                                    'interval' => 'day',
                                    'method'   => 'calc_daily_max',
                                    'argument' => 'soc',
                                    'comment'  => '(decimal)',
                                    'unit'     => '%',
                                    'priority' => 2,
                                    'order'    => $order ++,
                                );
                        */

        return $defns;
    }


    /**
     * READ_DEVICE
     * invoke newmodbus binary, and scrape the output
     * store all registers in this->regsiters
     *
     * @args    nil
     * @return  (bool) success
     *
     **/

    protected function parse_data( $data ) {

        $lines = explode( "\n", $data );

        // first line is date time
        preg_match( '/\[(.*)\]/', array_shift( $lines ), $regs );
        if ( ! isset( $regs[1] ) ) {
            throw new \Exception( 'Invalid data file: missing or invalid date/time.' );
        }

        $time = strtotime( $regs[1] );

        foreach ( $lines as $line ) {
            if ( ! $line || preg_match( "/^[\[-]/", $line ) ) {
                continue;
            }
            list( $register, $value ) = explode( ":", $line );
            $this->registers[ $register ] = $value;
        }
        //$this->registers[16387] = 0;

        //convert raw registers to decimal datapoints
        $data = [];
        foreach ( $this->datapoints() as $label => $datapoint ) {
            $args = [];
            if ( isset( $datapoint['arguments'] ) ) {
                $args = is_array( $datapoint['arguments'] ) ? $datapoint['arguments'] : [ $datapoint['arguments'] ];
            }
            $args [] = $data;

            $data[ $label ] = [
                'name'  => $datapoint['name'],
                'value' => call_user_func_array( [ $this, $datapoint['method'] ], $args ),
                'unit'  => $datapoint['unit'],
            ];
        }

        return [
            'timestamp' => $time,
            'data'      => $data,
        ];
    }


    /**
     * GET_REGISTER
     * helper to read_device
     * evaluates the logic to combine registers or extract partial registers
     * works on a single dp
     * lazy expression parser, fix
     *
     * @args   (string) expresssion
     * @return (decimal) dp value
     *
     **/

    protected function get_register_expr( $expression ) {

        //replace register addresses with values
        $tail       = $expression;
        $expression = '';
        $decimal    = NULL;
        while ( preg_match( "/^(.*?)\[(\d+)\](.*)$/s", $tail, $m ) ) {
            $expression .= $m[1];
            $reg        = $m[2];
            $tail       = $m[3];
            $expression .= $this->registers[ $reg ];
        }
        $expression = '$decimal=' . $expression . $tail . ';';

        //use eval to evaluate the bitwise logic, nasty
        //some sort of expression parser is needed there
        eval( $expression );

        return $decimal;
    }

    protected function get_register( $number ) {
        return $this->registers[ $number ];
    }

    public function get_register_dec( $number ) {
        return $this->get_register( $number );
    }

    /**
     * Converts a signed hexadecimal number to decimal
     *
     * @param $hex hexadecimal signed 16bit
     *
     * @return float
     */
    protected function get_register_dec_signed( $number ) {
        $_hex = unpack( "s", pack( "s", ( $this->get_register( $number ) ) ) );

        return reset( $_hex );
    }

    public function get_register_flt( $number, $decs = 1 ) {
        return number_format( $this->get_register( $number ), $decs );
    }

    public function get_register_flt_signed( $number, $decs = 1 ) {
        return number_format( $this->get_register_dec_signed( $number ), $decs );
    }

    public function get_register10_flt( $number, $decs = 1 ) {
        return number_format( $this->get_register( $number ) / 10, $decs );
    }

    public function get_register10_flt_signed( $number, $decs = 1 ) {
        return number_format( $this->get_register_dec_signed( $number ) / 10, $decs );
    }


    #####################################################################################################################
    ###
    ###  DERIVATIONS
    ###
    #####################################################################################################################


    /**
     * TRANSLATE_STAGE
     * derived method to map the classic charge stage to more helpful things
     * operates on the periodic array
     *
     * @args   (string) dp
     * @return (array)  values
     *
     **/

    protected function translate_stage( $arg, $data ) {

        //map native classic charge states to english
        $state_raw = [
            0  => 'Sleep',
            3  => 'Absorb',
            4  => 'Bulk',
            5  => 'Float',
            6  => 'Float~', //inaptly named 'float mmpt', ie failing to hold float voltage
            7  => 'EQ',
            18 => 'EQ~',
            10 => 'HyperVoc',
        ];
        //map to linear charge states, ie still an integer but in order, duh!
        $state_map = [
            0  => 2, //sleep
            4  => 3, //bulk
            3  => 4, //abs
            5  => 5, //float
            6  => 5, //float
            7  => 6, //eq
            18 => 6, //eq
            10 => 7  //voc
        ];

        if ( $arg == 'word' ) {
            return $state_raw[ $data['cstate']['value'] ];
        } elseif ( $arg == 'linear' ) {

            return $state_map[ $data['cstate']['value'] ];
        }
    }

    /**
     * TRANSLATE_STAGE
     * derived method to map the classic charge stage to more helpful things
     * operates on the periodic array
     *
     * @args   (string) dp
     * @return (array)  values
     *
     **/

    protected function translate_state( $data ) {

        //map native classic states to english
        $state_raw = [
            0 => 'Resting',
            1 => 'Waking',
            2 => 'Waking',
            3 => 'Active',
            4 => 'Active',
            6 => 'Active',
        ];

        return $state_raw[ $data['state']['value'] ];
    }

    /**
     * TRANSLATE_RESTING
     * derived method to map the classic reason of resting to more helpful things
     * operates on the periodic array
     *
     * @args   (string) dp
     * @return (array)  values
     *
     **/

    protected function translate_resting( $data ) {

        $raw = [
            '1'  => "Anti-Click. Not enough power available (Wake Up)",
            '2'  => "Insane Ibatt Measurement (Wake Up)",
            '3'  => "Negative Current (load on PV input ?) (Wake Up)",
            '4'  => "PV Input Voltage lower than Battery V (Vreg state)",
            '5'  => "Too low of power out and Vbatt below set point for > 90 seconds",
            '6'  => "FET temperature too high (Cover is on maybe ?)",
            '7'  => "Ground Fault Detected",
            '8'  => "Arc Fault Detected",
            '9'  => "Too much negative current while operating (backfeed from battery out of PV input)",
            '10' => "Battery is less than 8.0 Volts",
            '11' => "PV input is available but V is rising too slowly. Low Light or bad connection (Solar mode)",
            '12' => "Voc has gone down from last Voc or low light. Re-check (Solar mode)",
            '13' => "Voc has gone up from last Voc enough to be suspicious. Re-check (Solar mode)",
            '14' => "PV input is available but V is rising too slowly. Low Light or bad connection (Solar mode)",
            '15' => "Voc has gone down from last Voc or low light. Re-check (Solar mode)",
            '16' => "Mppt MODE is OFF (Usually because user turned it off)",
            '17' => "PV input is higher than operation range (too high for 150V Classic)",
            '18' => "PV input is higher than operation range (too high for 200V Classic)",
            '19' => "PV input is higher than operation range (too high for 250V or 250KS)",
            '22' => "Average Battery Voltage is too high above set point",
            '25' => "Battery Voltage too high of Overshoot (small battery or bad cable ?)",
            '26' => "Mode changed while running OR Vabsorb raised more than 10.0 Volts at once OR Nominal\nVbatt changed by modbus command AND MpptMode was ON when changed",
            '27' => "bridge center == 1023 (R132 might have been stuffed) This turns MPPT Mode to OFF",
            '28' => "NOT Resting but RELAY is not engaged for some reason",
            '29' => "ON/OFF stays off because WIND GRAPH is illegal (current step is set for > 100 amps)",
            '30' => "PkAmpsOverLimit... Software detected too high of PEAK output current",
            '31' => "AD1CH.IbattMinus > 900 Peak negative battery current > 90.0 amps (Classic 250)",
            '32' => "Aux 2 input commanded Classic off. for HI or LO (Aux2Function == 15 or 16)",
            '33' => "OCP in a mode other than Solar or PV-Uset",
            '34' => "AD1CH.IbattMinus > 900 Peak negative battery current > 90.0 amps (Classic 150, 200)",
        ];

        return $raw[ $data['restingreason']['value'] ];
    }

    /**
     * TRANSLATE_RESTING_SHORT
     * derived method to map the classic reason of resting to more helpful things
     * operates on the periodic array
     *
     * @args   (string) dp
     * @return (array)  values
     *
     **/

    protected function translate_resting_short( $data ) {

        $code = $data['restingreason']['value'];

        switch ( $code ) {
            case 2:
                $reason = "Battery Current Re-Calc";
                break;
            case 3:
                $reason = "Negative Current";
                break;
            case 4:
                $reason = "Input Voltage Lower than Battery Voltage";
                break;
            case 6:
                $reason = "FET Temp High";
                break;
            case 7:
                $reason = "Ground Fault";
                break;
            case 8:
                $reason = "Arc Fault";
                break;
            case 9:
                $reason = "Negative Current";
                break;
            case 10:
                $reason = "Very Low Battery Voltage";
                break;
            case 13:
                $reason = "Suspicious Voc jump";
                break;
            case 1:
            case 5:
            case 11:
            case 12:
            case 14:
            case 15:
                $reason = "Low Light";
                break;
            case 16:
                $reason = "MPPT Mode is OFF";
                break;
            case 17:
            case 18:
            case 19:
                $reason = "Input Voltage is too high";
                break;
            case 22:
                $reason = "Battery Voltage is too high";
                break;
            case 25:
                $reason = "Battery overshoot";
                break;
            case 26:
                $reason = "Abrupt Modbus Change";
                break;
            case 27:
                $reason = "Bridge Center High R132";
                break;
            case 28:
                $reason = "Relay Error";
                break;
            case 29:
                $reason = "Reload Wind Curve";
                break;
            default:
                $reason = "Unknown";
        };

        return $reason;
    }

    /**
     * CALC_EFFICIENCY
     * custom method to divide Pin by Pout , pretty shitty, as classic ipv is not accurate
     * operates on the periodic array
     *
     * @args   (string) arg
     * @return (array) values
     *
     **/

    protected function calc_efficiency( $data ) {
        $v    = $data['state']['value'];
        $pin  = $data['ipv']['value'] * $data['vpv']['value'];
        $pout = $data['iout']['value'] * $data['vout']['value'];
        $val  = $pout ? $pin / $pout * 100 : 0;

        return number_format( $val, 0 );
    }

    /**
     * CALC_LOAD_DATA
     * WBJR periodic derivations, load current etc
     * operates on the periodic array
     *
     * @args   (string) arg
     * @return (array) values
     *
     **/

    protected function calc_load_data( $arg, $datapoints ) {

        $vout  = $datapoints['vout']['value'];
        $iout  = $datapoints['iout']['value'];
        $ibat  = $datapoints['ibat']['value'];
        $iload = $iout - $ibat; //ibat is positive for charge.

        if ( $arg == 'iload' ) {
            $val = $iload;
        }
        if ( $arg == 'pload' ) {
            $val = $iload * $vout;
        }
        if ( $arg == 'iabsbat' ) {
            $val = abs( $ibat );
        }
        if ( $arg == 'ichgbat' ) {
            $val = $ibat > 0 ? $ibat : 0;
        }
        if ( $arg == 'idisbat' ) {
            $val = $ibat < 0 ? - $ibat : 0;
        }
        if ( $arg == 'batstate' ) {
            $val = $ibat > 0 ? "Charging" : "Discharging";
        }

        return ( $arg == 'batstate' ) ? $val : number_format( $val, 1 );
    }


    /**
     * CALC_WBJR_DERIV
     * derivation for WBJR daily agregations
     * operates on the day series
     *
     * @args   (string)  stageword
     * @return (string)  value
     *
     **/

    protected function calc_wbjr_deriv( $arg ) {

        $len   = $this->settings['sample_interval'] / 60;
        $tally = 0;
        foreach ( $this->datapoints['state']->data as $n => $state ) {
            $vout  = $this->datapoints['vout']->data[ $n ];
            $iout  = $this->datapoints['iout']->data[ $n ];
            $ibat  = $this->datapoints['ibat']->data[ $n ];
            $iload = $iout - $ibat; //ibat is positive for charge.

            if ( $arg == 'whload' ) {
                $tally += ( $iload * $vout * $len );
            }
            if ( $arg == 'ahcharge' and $ibat < 0 ) {
                $tally += ( abs( $ibat ) * $len );
            }
            if ( $arg == 'ahdischarge' and $ibat > 0 ) {
                $tally += ( abs( $ibat ) * $len );
            }
        }
        $tally = $tally / 60;
        $tally = round( $tally, 1 );

        return $tally;
    }


    /**
     * CALC_DAYS_SINCE
     * derivation for days since float/eq, etc
     * operates on the day series
     *
     * @args   (string)  stageword
     * @return (string)  value
     *
     **/

    protected function calc_days_since( $arg ) {

        $d = date( "Y-m-d" );

        //work backwards from today, making sure each day is present
        $dn = 0;
        $n  = count( $this->datetimes['day'] ) - 1;
        while ( $n >= 0 and $dn < 30 ) {
            if ( $d == $this->datetimes['day'][ $n ] ) {
                if ( $arg == 'float' and isset( $this->datapoints['durfloat']->day_data[ $n ] )
                                         and $this->datapoints['durfloat']->day_data[ $n ]
                ) {
                    break;
                }
                $n --;
            }
            $d = date( "Y-m-d", strtotime( "$d -1 day" ) );
            $dn ++;
        }

        return $dn;
    }


    /**
     * CALC_DAILY_DURATION
     * derivation for time spent in each stage
     * operates on the periodic array
     *
     * @args   (string)  stageword
     * @return (string)  value
     *
     **/

    protected function calc_daily_duration( $arg ) {
        $len   = $this->settings['sample_interval'] / 60;
        $tally = 0;
        foreach ( $this->datapoints['state']->data as $n => $state ) {
            if ( $arg == 'bulk' and $state == 4 ) {
                $tally += $len;
            }
            if ( $arg == 'absorb' and $state == 3 ) {
                $tally += $len;
            }
            if ( $arg == 'float' and ( $state > 4 ) and ( $state < 7 ) ) {
                $tally += $len;
            }
        }
        $tally = $tally / 60;
        $tally = round( $tally, 1 );

        return $tally;
    }


    /**
     * CALC_DAILY_SUM
     * custom method to derive energy produced in each stage
     * operates on the periodic array, returns a single value
     *
     * @args   (string) 'dplabel/stage'
     * @return (string) value
     *
     **/

    protected function calc_daily_sum( $arg ) {
        $len   = $this->settings['sample_interval'] / 60;
        $tally = 0;
        foreach ( $this->datapoints['state']->data as $n => $state ) {
            $pout = $this->datapoints['pout']->data[ $n ];
            if ( $arg == 'pout/bulk' and ( $state == 4 ) ) {
                $tally += ( $pout * $len );
            }
            if ( $arg == 'pout/absorb' and ( $state == 3 ) ) {
                $tally += ( $pout * $len );
            }
            if ( $arg == 'pout/float' and ( $state < 7 ) and ( $state > 4 ) ) {
                $tally += ( $pout * $len );
            }
            if ( $arg == 'pout/total' ) {
                $tally += ( $pout * $len );
            }
        }
        $tally = $tally / 60;
        $tally = round( $tally, 0 );

        return $tally;
    }


    /**
     * CALC_REST_VOLTAGE
     * custom method to derive early morning vbat
     * operates on the periodic array, returns a single value
     *
     * @args   (time) '05:00'
     * @return (string) value
     *
     **/

    protected function calc_rest_voltage( $arg ) {
        $rest = 0;
        $d1   = date( "H:i", strtotime( "$arg -30 minutes" ) );
        $d2   = date( "H:i", strtotime( $arg ) );
        if ( $d2 < $arg ) {
            return '';
        }
        foreach ( $this->datapoints['vout']->data as $n => $vbat ) {
            $d3 = date( "H:i", strtotime( $this->datetimes['periodic'][ $n ] ) );
            if ( $d3 < $d1 ) {
                continue;
            }
            if ( $d3 > $d2 ) {
                continue;
            }
            $rest = max( (float) $rest, (float) $vbat );
        }

        return number_format( $rest, 1 );
    }

    static function lsb( $in ) {
        return ( $in & 0x00ff );
    }

    static function msb( $in ) {
        return ( $in >> 8 );
    }

    static function BITS( $in, $start, $stop = - 1 ) {
        $bitmask = 0;
        if ( $stop == - 1 ) {
            $stop = $start;
        }
        for ( $i = $stop; $i <= $start; $i ++ ) {
            $bitmask += pow( 2, $i );
        }

        return ( $in & $bitmask >> pow( 2, $stop ) );
    }
}


?>
