<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
use App\Performance;
use App\Result;
use DB;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Excel;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function teamPerformance($month, $year, $daily_work_hours)
    {
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $daily_work_hours = (float)$daily_work_hours;
        $this->date_start = new Carbon('last day of last month '. $months[(int)$month-1] .' '. $year);
        $this->date_end = new Carbon('first day of next month'. $months[(int)$month-1] .' '. $year);

        $this->projects = DB::table('projects')->get();

        foreach ($this->projects as $project_key => $project_value) {
            // for each projects fetch its positions
            $positions = DB::table('positions')->where('project_id', $project_value->id)->get();
            $project_value->positions = $positions;

            foreach ($positions as $position_key => $position_value) {
                /* Head Count */

                $position_head_count_array = array();
                // instantiate date end
                $date_end = Carbon::parse('first Monday of'. $months[(int)$month-1] .' '. (int)$year)->addWeek();
                // start at first monday of the month then increment it weekly 
                for ($date_start = Carbon::parse('first Monday of'. $months[(int)$month-1] .' '. (int)$year); $date_start->lt($this->date_end); $date_start->addWeek()) { 
                    // fetch the performances by members to check the positions
                    $performances = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->where('performances.position_id', $position_value->id)
                        ->whereBetween('performances.date_start', [$date_start, $date_end])
                        ->whereNull('performances.deleted_at')
                        ->groupBy('performances.member_id')
                        ->get();

                    array_push($position_head_count_array, $performances);

                    // increment the date end
                    $date_end->addWeek();              
                }
                // for each positions count the members per week and compare its head count
                foreach ($position_head_count_array as $position_head_count_array_key => $position_head_count_array_value) {
                    if($position_head_count_array_key == 0){
                        $position_value->head_count = count($position_head_count_array_value);
                    }
                    else{
                        $position_value->head_count = $position_value->head_count < count($position_head_count_array_value) ? count($position_head_count_array_value) : $position_value->head_count;
                    }
                }

                $project_value->beginner_total_output = 0;
                $project_value->beginner_total_man_hours = 0;
                $project_value->beginner_total_average_output = 0;
                
                $project_value->moderately_experienced_total_output = 0;
                $project_value->moderately_experienced_total_man_hours = 0;
                $project_value->moderately_experienced_total_average_output = 0;
                
                $project_value->experienced_total_output = 0;
                $project_value->experienced_total_man_hours = 0;
                $project_value->experienced_total_average_output = 0;
                $project_value->beginner = array();
                $project_value->moderately_experienced = array();
                $project_value->experienced = array();

                $beginner = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->where('members.experience', 'Beginner')
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $project_value->id)
                    ->whereNull('performances.deleted_at')
                    ->get();

                if($beginner)
                {
                    foreach ($beginner as $beginnerKey => $beginnerValue) {
                         $project_value->beginner_total_output += $beginnerValue->output;
                         $project_value->beginner_total_man_hours += $beginnerValue->hours_worked;
                    }

                    $project_value->beginner_total_average_output = $project_value->beginner_total_output / $project_value->beginner_total_man_hours * $daily_work_hours;
                    
                    $project_value->beginner = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->where('members.experience', 'Beginner')
                        ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                        ->whereNull('performances.deleted_at')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $project_value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($project_value->beginner as $beginnerKey => $beginnerValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->where('members.experience', 'Beginner')
                            ->where('performances.position_id', $beginnerValue->position_id)
                            ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $project_value->id)
                            ->whereNull('performances.deleted_at')
                            ->get();

                        $beginnerValue->total_output = 0;
                        $beginnerValue->total_man_hours = 0;
                        $beginnerValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $beginnerValue->total_output += $queryValue->output;
                                $beginnerValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $beginnerValue->total_average_output = round($beginnerValue->total_output / $beginnerValue->total_man_hours * $daily_work_hours, 2);
                        }
                    }
                }

                $moderately_experienced = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->where('members.experience', 'Moderately Experienced')
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $project_value->id)
                    ->whereNull('performances.deleted_at')
                    ->get();

                if($moderately_experienced)
                {
                    foreach ($moderately_experienced as $moderatelyExperiencedKey => $moderatelyExperiencedValue) {
                         $project_value->moderately_experienced_total_output += $moderatelyExperiencedValue->output;
                         $project_value->moderately_experienced_total_man_hours += $moderatelyExperiencedValue->hours_worked;
                    }

                    $project_value->moderately_experienced_total_average_output = $project_value->moderately_experienced_total_output / $project_value->moderately_experienced_total_man_hours * $daily_work_hours;
                    
                    $project_value->moderately_experienced = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->where('members.experience', 'Moderately Experienced')
                        ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $project_value->id)
                        ->whereNull('performances.deleted_at')
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($project_value->moderately_experienced as $moderatelyExperiencedKey => $moderatelyExperiencedValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->where('members.experience', 'Moderately Experienced')
                            ->where('performances.position_id', $moderatelyExperiencedValue->position_id)
                            ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $project_value->id)
                            ->whereNull('performances.deleted_at')
                            ->get();

                        $moderatelyExperiencedValue->total_output = 0;
                        $moderatelyExperiencedValue->total_man_hours = 0;
                        $moderatelyExperiencedValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $moderatelyExperiencedValue->total_output += $queryValue->output;
                                $moderatelyExperiencedValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $moderatelyExperiencedValue->total_average_output = round($moderatelyExperiencedValue->total_output / $moderatelyExperiencedValue->total_man_hours * $daily_work_hours, 2);
                        }
                    }
                }

                $experienced = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->where('members.experience', 'Experienced')
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $project_value->id)
                    ->whereNull('performances.deleted_at')
                    ->get();

                if($experienced)
                {
                    foreach ($experienced as $experiencedKey => $experiencedValue) {
                         $project_value->experienced_total_output += $experiencedValue->output;
                         $project_value->experienced_total_man_hours += $experiencedValue->hours_worked;
                    }

                    $project_value->experienced_total_average_output = $project_value->experienced_total_output / $project_value->experienced_total_man_hours * $daily_work_hours;
                    
                    $project_value->experienced = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->where('members.experience', 'Experienced')
                        ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $project_value->id)
                        ->groupBy('positions.id')
                        ->whereNull('performances.deleted_at')
                        ->get();

                    foreach ($project_value->experienced as $experiencedKey => $experiencedValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->where('members.experience', 'Experienced')
                            ->where('performances.position_id', $experiencedValue->position_id)
                            ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $project_value->id)
                            ->whereNull('performances.deleted_at')
                            ->get();

                        $experiencedValue->total_output = 0;
                        $experiencedValue->total_man_hours = 0;
                        $experiencedValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $experiencedValue->total_output += $queryValue->output;
                                $experiencedValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $experiencedValue->total_average_output = round($experiencedValue->total_output / $experiencedValue->total_man_hours * $daily_work_hours, 2);
                        }
                    }
                }

                $project_value->overall_total_output = $project_value->beginner_total_output + $project_value->moderately_experienced_total_output + $project_value->experienced_total_output;
                $project_value->overall_total_man_hours = $project_value->beginner_total_man_hours + $project_value->moderately_experienced_total_man_hours + $project_value->experienced_total_man_hours;
                if(!$project_value->overall_total_man_hours)
                {
                    $project_value->overall_total_average_output = 0;
                }
                else{
                    $project_value->overall_total_average_output = round($project_value->overall_total_output / $project_value->overall_total_man_hours * $daily_work_hours, 2);
                }
            }
        }

        // return $this->projects;  

        Excel::create('PQR Project Summary Report '. $months[(int)$month-1] .' '. $year, function($excel){
            foreach ($this->projects as $key => $value) {
                $this->details = $value;
                $excel->sheet($value->name, function($sheet) {
                    $sheet->loadView('excel.team-performance')
                        ->with('details', $this->details);
                });
            }
        })->download('xlsx');
    }

    public function searchMonthly(Request $request)
    {
        $all = array();
        $daily_work_hours = (float)$request->daily_work_hours;
        $this->date_start = new Carbon('last day of last month '. $request->month .' '. $request->year);
        $this->date_end = new Carbon('first day of next month'. $request->month .' '. $request->year);
        $this->projects = DB::table('projects')->get();

        foreach ($this->projects as $key => $value) {
            $details = DB::table('performances')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->leftJoin('projects', 'projects.id', '=', 'performances.project_id')
                ->select(
                    '*',
                    'members.id as member_id',
                    'positions.name as position',
                    'projects.id as project_id'
                )
                ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                ->where('projects.id', $value->id)
                ->whereNull('performances.deleted_at')
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->orderBy('performances.date_start')
                // ->groupBy('positions.id')
                ->groupBy('members.id')
                ->get();

                // return $details;

            if(count($details)){            
                $details[0]->positions = DB::table('positions')->where('project_id', $value->id)->orderBy('name')->get();
                $details[0]->program_head_count = count($details);
                $details[0]->position_head_count = array();
                $details[0]->date_cover_start = Carbon::parse('first Monday of '. $request->month .' '. $request->year)->toFormattedDateString();
                $details[0]->date_cover_end = Carbon::parse('last Monday of '. $request->month .' '. $request->year)->addDays(5)->toFormattedDateString();

                foreach ($details[0]->positions as $positionKey => $positionValue) {
                    $position_head_count_array = array();
                    // instantiate date end
                    $date_end = Carbon::parse('first Monday of'. $request->month .' '. $request->year)->addWeek();
                    // start at first monday of the month then increment it weekly 
                    for ($date_start = Carbon::parse('first Monday of'. $request->month .' '. $request->year); $date_start->lt($this->date_end); $date_start->addWeek()) { 
                        // fetch the performances by members to check the positions
                        $performances = DB::table('performances')
                            ->join('positions', 'positions.id', '=', 'performances.position_id')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->select(
                                'performances.*',
                                'positions.name as position'
                            )
                            ->where('performances.position_id', $positionValue->id)
                            ->whereBetween('performances.date_start', [$date_start, $date_end])
                            ->whereNull('performances.deleted_at')
                            ->groupBy('performances.member_id')
                            ->get();

                        array_push($position_head_count_array, $performances);

                        // increment the date end
                        $date_end->addWeek();              
                    }
                    // for each positions count the members per week and compare its head count
                    foreach ($position_head_count_array as $position_head_count_array_key => $position_head_count_array_value) {
                        if($position_head_count_array_key == 0){
                            $head_count = count($position_head_count_array_value);
                        }
                        else{
                            $head_count = $head_count < count($position_head_count_array_value) ? count($position_head_count_array_value) : $head_count;
                        }
                    }

                    array_push($details[0]->position_head_count, $head_count);
                }

                $details[0]->beginner_total_output = 0;
                $details[0]->beginner_total_man_hours = 0;
                $details[0]->beginner_total_average_output = 0;
                
                $details[0]->moderately_experienced_total_output = 0;
                $details[0]->moderately_experienced_total_man_hours = 0;
                $details[0]->moderately_experienced_total_average_output = 0;
                
                $details[0]->experienced_total_output = 0;
                $details[0]->experienced_total_man_hours = 0;
                $details[0]->experienced_total_average_output = 0;

                $beginner = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->where('members.experience', 'Beginner')
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereNull('performances.deleted_at')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $value->id)
                    ->get();

                if($beginner)
                {
                    foreach ($beginner as $beginnerKey => $beginnerValue) {
                         $details[0]->beginner_total_output += $beginnerValue->output;
                         $details[0]->beginner_total_man_hours += $beginnerValue->hours_worked;
                    }

                    $details[0]->beginner_total_average_output = $details[0]->beginner_total_output / $details[0]->beginner_total_man_hours * $daily_work_hours;
                    
                    $details[0]->beginner = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->where('members.experience', 'Beginner')
                        ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                        ->whereNull('performances.deleted_at')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($details[0]->beginner as $beginnerKey => $beginnerValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->where('members.experience', 'Beginner')
                            ->where('performances.position_id', $beginnerValue->position_id)
                            ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                            ->whereNull('performances.deleted_at')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $value->id)
                            ->get();

                        $beginnerValue->total_output = 0;
                        $beginnerValue->total_man_hours = 0;
                        $beginnerValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $beginnerValue->total_output += $queryValue->output;
                                $beginnerValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $beginnerValue->total_average_output = round($beginnerValue->total_output / $beginnerValue->total_man_hours * $daily_work_hours, 2);
                        }
                    }
                }

                $moderately_experienced = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->where('members.experience', 'Moderately Experienced')
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereNull('performances.deleted_at')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $value->id)
                    ->get();

                if($moderately_experienced)
                {
                    foreach ($moderately_experienced as $moderatelyExperiencedKey => $moderatelyExperiencedValue) {
                         $details[0]->moderately_experienced_total_output += $moderatelyExperiencedValue->output;
                         $details[0]->moderately_experienced_total_man_hours += $moderatelyExperiencedValue->hours_worked;
                    }

                    $details[0]->moderately_experienced_total_average_output = $details[0]->moderately_experienced_total_output / $details[0]->moderately_experienced_total_man_hours * $daily_work_hours;
                    
                    $details[0]->moderately_experienced = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->where('members.experience', 'Moderately Experienced')
                        ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                        ->whereNull('performances.deleted_at')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($details[0]->moderately_experienced as $moderatelyExperiencedKey => $moderatelyExperiencedValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->whereNull('performances.deleted_at')
                            ->where('members.experience', 'Moderately Experienced')
                            ->where('performances.position_id', $moderatelyExperiencedValue->position_id)
                            ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $value->id)
                            ->get();

                        $moderatelyExperiencedValue->total_output = 0;
                        $moderatelyExperiencedValue->total_man_hours = 0;
                        $moderatelyExperiencedValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $moderatelyExperiencedValue->total_output += $queryValue->output;
                                $moderatelyExperiencedValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $moderatelyExperiencedValue->total_average_output = round($moderatelyExperiencedValue->total_output / $moderatelyExperiencedValue->total_man_hours * $daily_work_hours, 2);
                        }
                    }
                }

                $experienced = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->whereNull('performances.deleted_at')
                    ->where('members.experience', 'Experienced')
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $value->id)
                    ->get();

                if($experienced)
                {
                    foreach ($experienced as $experiencedKey => $experiencedValue) {
                         $details[0]->experienced_total_output += $experiencedValue->output;
                         $details[0]->experienced_total_man_hours += $experiencedValue->hours_worked;
                    }

                    $details[0]->experienced_total_average_output = round($details[0]->experienced_total_output / $details[0]->experienced_total_man_hours * $daily_work_hours, 2);
                    
                    $details[0]->experienced = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->whereNull('performances.deleted_at')
                        ->where('members.experience', 'Experienced')
                        ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($details[0]->experienced as $experiencedKey => $experiencedValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->whereNull('performances.deleted_at')
                            ->where('members.experience', 'Experienced')
                            ->where('performances.position_id', $experiencedValue->position_id)
                            ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $value->id)
                            ->get();

                        $experiencedValue->total_output = 0;
                        $experiencedValue->total_man_hours = 0;
                        $experiencedValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $experiencedValue->total_output += $queryValue->output;
                                $experiencedValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $experiencedValue->total_average_output = round($experiencedValue->total_output / $experiencedValue->total_man_hours * $daily_work_hours, 2);
                        }
                    }
                }

                $details[0]->overall_total_output = $details[0]->beginner_total_output + $details[0]->moderately_experienced_total_output + $details[0]->experienced_total_output;
                $details[0]->overall_total_man_hours = $details[0]->beginner_total_man_hours + $details[0]->moderately_experienced_total_man_hours + $details[0]->experienced_total_man_hours;
                $details[0]->overall_total_average_output = round($details[0]->overall_total_output / $details[0]->overall_total_man_hours * $daily_work_hours, 2);
            }

            foreach ($details as $key1 => $value1) {
                $this->productivity_average = 0;
                $this->quality_average = 0;

                $results = DB::table('performances')
                    ->leftJoin('results', 'results.performance_id', '=', 'performances.id')
                    ->leftJoin('members', 'members.id', '=', 'performances.member_id')
                    ->leftJoin('positions', 'positions.id', '=', 'performances.position_id')
                    ->select(
                        '*',
                        'performances.id as performance_id',
                        'positions.name as position',
                        DB::raw('DATE_FORMAT(performances.date_start, "%b. %d") as date_start'),
                        DB::raw('DATE_FORMAT(performances.date_end, "%b. %d") as date_end')
                    )
                    ->whereNull('performances.deleted_at')
                    ->where('members.id', $value1->member_id)
                    ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    // ->whereBetween('performances.date_end', [$this->date_start, $this->date_end])
                    ->orderBy('positions.name')
                    ->orderBy('members.full_name')
                    ->orderBy('performances.date_start')
                    ->get();

                // foreach members performance add its results 
                foreach ($results as $key2 => $value4) {
                    $this->productivity_average += $value4->productivity;
                    $this->quality_average += $value4->quality;
                }
                
                // average its results
                $this->productivity_average = round($this->productivity_average / count($results), 1); 
                $this->quality_average = round($this->quality_average / count($results), 1);

                $details[$key1]->results = $results;
                $details[$key1]->productivity_average = $this->productivity_average;
                $details[$key1]->quality_average = $this->quality_average;

                $quality_target = DB::table('targets')
                    ->join('positions', 'positions.id', '=', 'targets.position_id')
                    ->join('members', 'members.experience', '=', 'targets.experience')
                    ->select('*')
                    ->where('targets.position_id', $value1->position_id)
                    ->where('targets.experience', $value1->experience)
                    ->where('targets.type', 'Quality')
                    ->first();

                $details[$key1]->quota = (($this->productivity_average >= 100) && ($this->quality_average >= $quality_target->value)) ? 'Met' : 'Not met';
                
            }

            array_push($all, $details);
        }

        return $all;
    }
    public function monthly()
    {
        $all = array();

        $this->date_start = new Carbon('last day of last month'); // last month
        $this->date_end = new Carbon('first day of next month'); // next month
        $this->projects = DB::table('projects')->get();

        foreach ($this->projects as $key => $value) {
            $details = DB::table('performances')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->leftJoin('projects', 'projects.id', '=', 'performances.project_id')
                ->select(
                    '*',
                    'members.id as member_id',
                    'positions.name as position',
                    'projects.id as project_id',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d") as date_end')
                )
                ->whereNull('performances.deleted_at')
                ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                ->where('projects.id', $value->id)
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->orderBy('performances.date_start')
                ->groupBy('members.id')
                ->get();

            if(count($details)){            
                $details[0]->positions = DB::table('positions')->where('project_id', $value->id)->orderBy('name')->get();
                $details[0]->program_head_count = count($details);
                $details[0]->position_head_count = array();
                $details[0]->date_cover_start = Carbon::parse('first Monday of this month')->toFormattedDateString();
                $details[0]->date_cover_end = Carbon::parse('last Monday of this month')->addDays(5)->toFormattedDateString();

                foreach ($details[0]->positions as $positionKey => $positionValue) {
                    $position_head_count_array = array();
                    // instantiate date end
                    $date_end = Carbon::parse('first Monday of this month')->addWeek();
                    // start at first monday of the month then increment it weekly 
                    for ($date_start = Carbon::parse('first Monday of this month'); $date_start->lt($this->date_end); $date_start->addWeek()) { 
                        // fetch the performances by members to check the positions
                        $performances = DB::table('performances')
                            ->join('positions', 'positions.id', '=', 'performances.position_id')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->select(
                                'performances.*',
                                'positions.name as position'
                            )
                            ->whereNull('performances.deleted_at')
                            ->where('performances.position_id', $positionValue->id)
                            ->whereBetween('performances.date_start', [$date_start, $date_end])
                            ->groupBy('performances.member_id')
                            ->get();

                        array_push($position_head_count_array, $performances);

                        // increment the date end
                        $date_end->addWeek();              
                    }
                    // for each positions count the members per week and compare its head count
                    foreach ($position_head_count_array as $position_head_count_array_key => $position_head_count_array_value) {
                        if($position_head_count_array_key == 0){
                            $head_count = count($position_head_count_array_value);
                        }
                        else{
                            $head_count = $head_count < count($position_head_count_array_value) ? count($position_head_count_array_value) : $head_count;
                        }
                    }

                    array_push($details[0]->position_head_count, $head_count);
                }

                $details[0]->beginner_total_output = 0;
                $details[0]->beginner_total_man_hours = 0;
                $details[0]->beginner_total_average_output = 0;
                
                $details[0]->moderately_experienced_total_output = 0;
                $details[0]->moderately_experienced_total_man_hours = 0;
                $details[0]->moderately_experienced_total_average_output = 0;
                
                $details[0]->experienced_total_output = 0;
                $details[0]->experienced_total_man_hours = 0;
                $details[0]->experienced_total_average_output = 0;

                $beginner = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->whereNull('performances.deleted_at')
                    ->where('members.experience', 'Beginner')
                    // ->where('performances.daily_work_hours', 'like', $daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $value->id)
                    ->get();

                if($beginner)
                {
                    foreach ($beginner as $beginnerKey => $beginnerValue) {
                         $details[0]->beginner_total_output += $beginnerValue->output;
                         $details[0]->beginner_total_man_hours += $beginnerValue->hours_worked;
                    }

                    $details[0]->beginner_total_average_output = $details[0]->beginner_total_output / $details[0]->beginner_total_man_hours * $details[0]->daily_work_hours;
                    
                    $details[0]->beginner = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->whereNull('performances.deleted_at')
                        ->where('members.experience', 'Beginner')
                        // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($details[0]->beginner as $beginnerKey => $beginnerValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->whereNull('performances.deleted_at')
                            ->where('members.experience', 'Beginner')
                            ->where('performances.position_id', $beginnerValue->position_id)
                            // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $value->id)
                            ->get();

                        $beginnerValue->total_output = 0;
                        $beginnerValue->total_man_hours = 0;
                        $beginnerValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $beginnerValue->total_output += $queryValue->output;
                                $beginnerValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $beginnerValue->total_average_output = round($beginnerValue->total_output / $beginnerValue->total_man_hours * $details[0]->daily_work_hours, 2);
                        }
                    }
                }

                $moderately_experienced = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->whereNull('performances.deleted_at')
                    ->where('members.experience', 'Moderately Experienced')
                    // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $value->id)
                    ->get();

                if($moderately_experienced)
                {
                    foreach ($moderately_experienced as $moderatelyExperiencedKey => $moderatelyExperiencedValue) {
                         $details[0]->moderately_experienced_total_output += $moderatelyExperiencedValue->output;
                         $details[0]->moderately_experienced_total_man_hours += $moderatelyExperiencedValue->hours_worked;
                    }

                    $details[0]->moderately_experienced_total_average_output = $details[0]->moderately_experienced_total_output / $details[0]->moderately_experienced_total_man_hours * $details[0]->daily_work_hours;
                    
                    $details[0]->moderately_experienced = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->whereNull('performances.deleted_at')
                        ->where('members.experience', 'Moderately Experienced')
                        // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($details[0]->moderately_experienced as $moderatelyExperiencedKey => $moderatelyExperiencedValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->whereNull('performances.deleted_at')
                            ->where('members.experience', 'Moderately Experienced')
                            ->where('performances.position_id', $moderatelyExperiencedValue->position_id)
                            // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $value->id)
                            ->get();

                        $moderatelyExperiencedValue->total_output = 0;
                        $moderatelyExperiencedValue->total_man_hours = 0;
                        $moderatelyExperiencedValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $moderatelyExperiencedValue->total_output += $queryValue->output;
                                $moderatelyExperiencedValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $moderatelyExperiencedValue->total_average_output = round($moderatelyExperiencedValue->total_output / $moderatelyExperiencedValue->total_man_hours * $details[0]->daily_work_hours, 2);
                        }
                    }
                }

                $experienced = DB::table('performances')
                    ->join('members', 'members.id', '=', 'performances.member_id')
                    ->select('performances.*')
                    ->whereNull('performances.deleted_at')
                    ->where('members.experience', 'Experienced')
                    // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    ->where('performances.project_id', $value->id)
                    ->get();

                if($experienced)
                {
                    foreach ($experienced as $experiencedKey => $experiencedValue) {
                         $details[0]->experienced_total_output += $experiencedValue->output;
                         $details[0]->experienced_total_man_hours += $experiencedValue->hours_worked;
                    }

                    $details[0]->experienced_total_average_output = round($details[0]->experienced_total_output / $details[0]->experienced_total_man_hours * $details[0]->daily_work_hours, 2);
                    
                    $details[0]->experienced = DB::table('performances')
                        ->join('positions', 'positions.id', '=', 'performances.position_id')
                        ->join('members', 'members.id', '=', 'performances.member_id')
                        ->select(
                            'performances.*',
                            'positions.name as position'
                        )
                        ->whereNull('performances.deleted_at')
                        ->where('members.experience', 'Experienced')
                        // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                        ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                        ->where('performances.project_id', $value->id)
                        ->groupBy('positions.id')
                        ->get();

                    foreach ($details[0]->experienced as $experiencedKey => $experiencedValue) {
                        $query = DB::table('performances')
                            ->join('members', 'members.id', '=', 'performances.member_id')
                            ->whereNull('performances.deleted_at')
                            ->where('members.experience', 'Experienced')
                            ->where('performances.position_id', $experiencedValue->position_id)
                            // ->where('performances.daily_work_hours', 'like', $details[0]->daily_work_hours.'%')
                            ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                            ->where('performances.project_id', $value->id)
                            ->get();

                        $experiencedValue->total_output = 0;
                        $experiencedValue->total_man_hours = 0;
                        $experiencedValue->total_average_output = 0;

                        if($query)
                        {
                            foreach ($query as $queryKey => $queryValue) {
                                $experiencedValue->total_output += $queryValue->output;
                                $experiencedValue->total_man_hours += $queryValue->hours_worked;
                            }

                            $experiencedValue->total_average_output = round($experiencedValue->total_output / $experiencedValue->total_man_hours * $details[0]->daily_work_hours, 2);
                        }
                    }
                }

                $details[0]->overall_total_output = $details[0]->beginner_total_output + $details[0]->moderately_experienced_total_output + $details[0]->experienced_total_output;
                $details[0]->overall_total_man_hours = $details[0]->beginner_total_man_hours + $details[0]->moderately_experienced_total_man_hours + $details[0]->experienced_total_man_hours;
                $details[0]->overall_total_average_output = round($details[0]->overall_total_output / $details[0]->overall_total_man_hours * $details[0]->daily_work_hours, 2);
            }

            foreach ($details as $key1 => $value1) {
                $this->productivity_average = 0;
                $this->quality_average = 0;

                $results = DB::table('performances')
                    ->leftJoin('results', 'results.performance_id', '=', 'performances.id')
                    ->leftJoin('members', 'members.id', '=', 'performances.member_id')
                    ->leftJoin('positions', 'positions.id', '=', 'performances.position_id')
                    ->select(
                        '*',
                        'performances.id as performance_id',
                        'positions.name as position',
                        DB::raw('DATE_FORMAT(performances.date_start, "%b. %d") as date_start'),
                        DB::raw('DATE_FORMAT(performances.date_end, "%b. %d") as date_end')
                    )
                    ->whereNull('performances.deleted_at')
                    ->where('members.id', $value1->member_id)
                    ->whereBetween('performances.date_start', [$this->date_start, $this->date_end])
                    // ->whereBetween('performances.date_end', [$this->date_start, $this->date_end])
                    ->orderBy('positions.name')
                    ->orderBy('members.full_name')
                    ->orderBy('performances.date_start')
                    ->get();

                // foreach members performance add its results 
                foreach ($results as $key2 => $value4) {
                    $this->productivity_average += $value4->productivity;
                    $this->quality_average += $value4->quality;
                }
                
                // average its results
                $this->productivity_average = round($this->productivity_average / count($results), 1); 
                $this->quality_average = round($this->quality_average / count($results), 1);

                $details[$key1]->results = $results;
                $details[$key1]->productivity_average = $this->productivity_average;
                $details[$key1]->quality_average = $this->quality_average;

                $quality_target = DB::table('targets')
                    ->join('positions', 'positions.id', '=', 'targets.position_id')
                    ->join('members', 'members.experience', '=', 'targets.experience')
                    ->select('*')
                    ->where('targets.position_id', $value1->position_id)
                    ->where('targets.experience', $value1->experience)
                    ->where('targets.type', 'Quality')
                    ->first();

                $details[$key1]->quota = (($this->productivity_average >= 100) && ($this->quality_average >= $quality_target->value)) ? 'Met' : 'Not met';
            
            }

            array_push($all, $details);
        }

        return $all;
    }

    public function downloadMonthlySummary($month, $year, $daily_work_hours)
    {
        $this->projects = DB::table('projects')->get();
        $this->month = $month;
        $this->year = $year;
        $this->date_start = $this->year.'-'.$this->month.'-'.'01';
        $this->date_end = $this->year.'-'.$this->month.'-'.'31';
        $this->date_start_format = date_create($this->date_start)->format("F Y");
        $this->date_end_format = date_create($this->date_end)->format("F Y");
        
        $this->reports_array = array();
        $this->members_array = array();

        $beginner_temp = array();
        $moderately_experienced_temp = array();
        $experienced_temp = array();
        $quality_temp = array();

        $this->beginner = array();
        $this->moderately_experienced = array();
        $this->experienced = array();
        $this->quality = array();
        $this->positions_array = array();

        foreach ($this->projects as $parentKey => $value) {
            $this->positions = DB::table('positions')
                ->where('project_id', $value->id)
                ->get();

            array_push($this->positions_array, $this->positions);

            foreach ($this->positions as $key => $value2) {
                $beginner = DB::table('targets')
                    ->where('type', 'Productivity')
                    ->where('position_id', $value2->id)
                    ->where('experience', 'Beginner')
                    ->first();

                $moderately_experienced = DB::table('targets')
                    ->where('type', 'Productivity')
                    ->where('position_id', $value2->id)
                    ->where('experience', 'Moderately Experienced')
                    ->first();

                $experienced = DB::table('targets')
                    ->where('type', 'Productivity')
                    ->where('position_id', $value2->id)
                    ->where('experience', 'Experienced')
                    ->first();

                $quality = DB::table('targets')
                    ->where('type', 'Quality')
                    ->where('position_id', $value2->id)
                    ->first();

                array_push($beginner_temp, $beginner);
                array_push($moderately_experienced_temp, $moderately_experienced);
                array_push($experienced_temp, $experienced);
                array_push($quality_temp, $quality);
            }
            // return $beginner_temp;
            array_push($this->beginner, $beginner_temp);
            array_push($this->moderately_experienced, $moderately_experienced_temp);
            array_push($this->experienced, $experienced_temp);
            array_push($this->quality, $quality_temp);

            $beginner_temp = array();
            $moderately_experienced_temp = array();
            $experienced_temp = array();
            $quality_temp = array();

            $reports = DB::table('performances')
                ->select(
                    '*',
                    DB::raw('DATE_FORMAT(date_start, "%b. %d") as date_start_formatted'),
                    DB::raw('DATE_FORMAT(date_end, "%b. %d") as date_end_formatted')
                )
                ->whereNull('deleted_at')
                ->where('project_id', $value->id)
                ->whereBetween('date_start', [$this->date_start, $this->date_end])
                ->where('daily_work_hours', 'like', $daily_work_hours)
                ->groupBy('date_start')
                ->get();

            // fetch all members of per project
            $members = DB::table('performances')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->leftJoin('projects', 'projects.id', '=', 'performances.project_id')
                ->select(
                    '*',
                    'members.id as member_id',
                    'positions.name as position',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d") as date_end')
                )
                ->whereNull('performances.deleted_at')
                ->where('projects.id', $value->id)
                ->groupBy('members.id')
                ->get();

            // foreach members fetch its performance
            foreach ($members as $key1 => $value3) {
                $this->productivity_average = 0;
                $this->quality_average = 0;
                $results = array();

                foreach ($reports as $report_key => $report_value) {
                    $query = DB::table('performances')
                        ->leftJoin('results', 'results.performance_id', '=', 'performances.id')
                        ->leftJoin('members', 'members.id', '=', 'performances.member_id')
                        ->leftJoin('positions', 'positions.id', '=', 'performances.position_id')
                        ->select(
                            '*',
                            'performances.id as performance_id',
                            'positions.name as position',
                            DB::raw('DATE_FORMAT(performances.date_start, "%b. %d") as date_start'),
                            DB::raw('DATE_FORMAT(performances.date_end, "%b. %d") as date_end')
                        )
                        ->whereNull('performances.deleted_at')
                        ->where('members.id', $value3->member_id)
                        ->whereBetween('performances.date_start', [$report_value->date_start, $report_value->date_end])
                        ->orderBy('positions.name')
                        ->orderBy('members.full_name')
                        ->first();

                    if($query){
                        array_push($results, $query);
                    }
                    else{
                        $blank = new Performance;
                        $blank->productivity = 0;
                        $blank->quality = 0;
                        array_push($results, $blank);
                    }

                    foreach ($results as $key2 => $value4) {
                        $this->productivity_average += $value4->productivity;
                        $this->quality_average += $value4->quality;
                    }
                }

                $this->productivity_average = round($this->productivity_average / count($reports), 1); 
                $this->quality_average = round($this->quality_average / count($reports), 1);

                $members[$key1]->results = $results;
                $members[$key1]->productivity_average = $this->productivity_average;
                $members[$key1]->quality_average = $this->quality_average;

                $quality_target = DB::table('targets')
                    ->join('positions', 'positions.id', '=', 'targets.position_id')
                    ->join('members', 'members.experience', '=', 'targets.experience')
                    ->select('*')
                    ->where('targets.position_id', $value3->position_id)
                    ->where('targets.experience', $value3->experience)
                    ->where('targets.type', 'Quality')
                    ->first();

                $members[$key1]->quota = (($this->productivity_average >= 100) && ($this->quality_average >= $quality_target->value)) ? 'Met' : 'Not met';

                // foreach members performance add its results 
                
                // average its results
            }

            array_push($this->members_array, $members);
            array_push($this->reports_array, $reports);
        }

        Excel::create('PQR Summary Report '. $this->date_start_format, function($excel){
            foreach ($this->projects as $key => $value) {
                $this->index = $key;
                $excel->sheet($value->name, function($sheet) {
                    $sheet->loadView('excel.monthly')
                        ->with('positions', $this->positions_array[$this->index])
                        ->with('members', $this->members_array[$this->index])
                        ->with('reports', $this->reports_array[$this->index])
                        ->with('beginner', $this->beginner[$this->index])
                        ->with('moderately_experienced', $this->moderately_experienced[$this->index])
                        ->with('experienced', $this->experienced[$this->index])
                        ->with('quality', $this->quality[$this->index]);
                });
            }
        })->download('xlsx');


    }
    public function downloadSummary($date_start, $date_end, $daily_work_hours)
    {
        global $report, $report_array, $position_array, $quality_array, $beginner_array, $moderately_experienced_array, $experienced_array;

        $report_array = array();
        $position_array = array();
        $quality_array = array();

        $beginner_array = array();
        $moderately_experienced_array = array();
        $experienced_array = array();

        $beginner_temp = array();
        $moderately_experienced_temp = array();
        $experienced_temp = array();
        $quality_temp = array();

        $date_start = date_create($date_start)->format("Y-m-d");
        $date_end = date_create($date_end)->format("Y-m-d");

        $report = DB::table('reports')
            ->join('projects', 'projects.id', '=', 'reports.project_id')
            ->join('departments', 'departments.id', '=', 'reports.department_id')
            ->select(
                '*',
                'reports.id as report_id',
                'projects.id as project_id', 
                'projects.name as project_name', 
                'departments.name as department_name'
            )
            ->whereNull('reports.deleted_at')
            ->where('reports.daily_work_hours', 'like', $daily_work_hours)
            ->where('reports.date_start', 'like', $date_start .'%')
            ->whereBetween('reports.date_end', [$date_start, $date_end])
            ->groupBy('reports.id')
            ->get();

        if(!count($report))
        {
            return 'No records found.';
        }

        // will fetch every performance and results for the specific report
        foreach ($report as $key => $value) {
            $reports = DB::table('reports')
                ->join('performances', 'performances.report_id', '=', 'reports.id')
                ->join('results', 'results.performance_id', '=', 'performances.id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->join('projects', 'projects.id', '=', 'reports.project_id')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->select(
                    'members.*',
                    'performances.*',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                    'results.*',
                    'projects.*',
                    'projects.name as project',
                    'positions.name as position'
                )
                ->whereNull('reports.deleted_at')
                ->where('performances.report_id', $value->report_id)
                ->where('results.report_id', $value->report_id)
                ->groupBy('performances.id')
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->get();

            // push each results to custom array
            array_push($report_array, $reports);

            $positions = DB::table('positions')
                ->where('project_id', $value->project_id)
                ->get();

            // push each results to custom array
            array_push($position_array, $positions);

            foreach ($positions as $key => $value) {
                $beginner = DB::table('targets')
                    ->where('type', 'Productivity')
                    ->where('position_id', $value->id)
                    ->where('experience', 'Beginner')
                    ->first();

                $moderately_experienced = DB::table('targets')
                    ->where('type', 'Productivity')
                    ->where('position_id', $value->id)
                    ->where('experience', 'Moderately Experienced')
                    ->first();

                $experienced = DB::table('targets')
                    ->where('type', 'Productivity')
                    ->where('position_id', $value->id)
                    ->where('experience', 'Experienced')
                    ->first();

                $quality = DB::table('targets')
                    ->where('type', 'Quality')
                    ->where('position_id', $value->id)
                    ->first();            

                array_push($beginner_temp, $beginner);
                array_push($moderately_experienced_temp, $moderately_experienced);
                array_push($experienced_temp, $experienced);
                array_push($quality_temp, $quality);
            }

            // push in for per report
            array_push($beginner_array, $beginner_temp);
            array_push($moderately_experienced_array, $moderately_experienced_temp);
            array_push($experienced_array, $experienced_temp);
            array_push($quality_array, $quality_temp);

            // reset the temp
            $beginner_temp = array();
            $moderately_experienced_temp = array();
            $experienced_temp = array();
            $quality_temp = array();
        }
        
        Excel::create('PQR '. $date_start . ' to ' . $date_end, function($excel) {
            global $report;

            foreach ($report as $key => $value) {
                global $index;
                $index = $key;
                $excel->sheet($value->project_name, function($sheet) {
                    global $report_array, $index, $quality_array, $position_array, $beginner_array, $moderately_experienced_array, $experienced_array;
                    $sheet->loadView('excel.weekly')
                        ->with('data', $report_array[$index])
                        ->with('positions', $position_array[$index])
                        ->with('beginner', $beginner_array[$index])
                        ->with('moderately_experienced', $moderately_experienced_array[$index])
                        ->with('experienced', $experienced_array[$index])
                        ->with('quality', $quality_array[$index]);
                });
            }

        })->download('xlsx');

    }
    public function download($reportID)
    {
        global $report, $details, $positions, $quality, $beginner, $moderately_experienced, $experienced;

        $details = DB::table('reports')
            ->join('projects', 'projects.id', '=', 'reports.project_id')
            ->join('departments', 'departments.id', '=', 'reports.department_id')
            ->select('*', 'projects.name as project_name', 'departments.name as department_name')
            ->where('reports.id', $reportID)
            ->first();

        $report = DB::table('reports')
            ->join('performances', 'performances.report_id', '=', 'reports.id')
            ->join('results', 'results.performance_id', '=', 'performances.id')
            ->join('positions', 'positions.id', '=', 'performances.position_id')
            ->join('projects', 'projects.id', '=', 'reports.project_id')
            ->join('members', 'members.id', '=', 'performances.member_id')
            ->select(
                'members.*',
                'performances.*',
                DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                'results.*',
                'projects.*',
                'projects.name as project',
                'positions.name as position',
                'positions.id as position_id',
                'projects.id as project_id'
            )
            ->whereNull('performances.deleted_at')
            ->whereNull('reports.deleted_at')
            ->where('performances.report_id', $reportID)
            ->where('results.report_id', $reportID)
            ->groupBy('performances.id')
            ->orderBy('positions.name')
            ->orderBy('members.full_name')
            ->get();

        foreach ($report as $key => $value) {
            $positions = DB::table('positions')
                ->where('project_id', $value->project_id)
                ->get();

            $beginner = DB::table('targets')
                ->where('type', 'Productivity')
                ->where('project_id', $value->project_id)
                ->where('experience', 'Beginner')
                ->get();

            $moderately_experienced = DB::table('targets')
                ->where('type', 'Productivity')
                ->where('project_id', $value->project_id)
                ->where('experience', 'Moderately Experienced')
                ->get();

            $experienced = DB::table('targets')
                ->where('type', 'Productivity')
                ->where('project_id', $value->project_id)
                ->where('experience', 'Experienced')
                ->get();

            $quality = DB::table('targets')
                ->where('type', 'Quality')
                ->where('project_id', $value->project_id)
                ->groupBy('position_id')
                ->get();
        }

        Excel::create('PQR - '. $details->department_name .' ('. $details->project_name.')', function($excel) {
            global $details;
            $excel->sheet($details->date_start .' to '. $details->date_end, function($sheet) {
                global $report, $quality, $positions, $beginner, $moderately_experienced, $experienced;
                $sheet->loadView('excel.weekly')
                    ->with('data',$report)
                    ->with('positions', $positions)
                    ->with('beginner', $beginner)
                    ->with('moderately_experienced', $moderately_experienced)
                    ->with('experienced', $experienced)
                    ->with('quality', $quality);
            });

        })->download('xlsx');
    }
    public function searchDepartment(Request $request, $id)
    {
        $report_array = array();

        $reports = DB::table('reports')
            ->join('performances', 'performances.report_id', '=', 'reports.id')
            ->join('results', 'results.performance_id', '=', 'performances.id')
            ->join('positions', 'positions.id', '=', 'performances.position_id')
            ->join('projects', 'projects.id', '=', 'reports.project_id')
            ->join('members', 'members.id', '=', 'performances.member_id')
            ->select(
                'members.*',
                'reports.id as report_id',
                'performances.*',
                DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                'results.*',
                'projects.*',
                'projects.name as project',
                'positions.name as position'
            )
            ->whereNull('reports.deleted_at')
            ->whereNull('performances.deleted_at')
            ->where('reports.department_id', $id)
            ->where('reports.date_start', 'like', '%'. $request->userInput .'%')
            ->orWhere('reports.date_end', 'like', '%'. $request->userInput .'%')
            ->orWhere('projects.name', 'like', '%'. $request->userInput .'%')
            ->groupBy('reports.id')
            ->orderBy('reports.date_start', 'desc')
            ->get();

        foreach ($reports as $key => $value) {
            $query = DB::table('reports')
                ->join('performances', 'performances.report_id', '=', 'reports.id')
                ->join('results', 'results.performance_id', '=', 'performances.id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->join('projects', 'projects.id', '=', 'reports.project_id')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->select(
                    'reports.id as report_id',
                    'members.*',
                    'performances.*',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                    'results.*',
                    'projects.*',
                    'projects.name as project',
                    'positions.name as position'
                )
                ->whereNull('reports.deleted_at')
                ->whereNull('performances.deleted_at')
                ->where('performances.report_id', $value->report_id)
                ->where('results.report_id', $value->report_id)
                ->groupBy('performances.id')
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->get();

            array_push($report_array, $query);
        }
        return response()->json($report_array);
    }

    public function search(Request $request)
    {
        $report_array = array();

        $reports = DB::table('reports')
            ->join('performances', 'performances.report_id', '=', 'reports.id')
            ->join('results', 'results.performance_id', '=', 'performances.id')
            ->join('positions', 'positions.id', '=', 'performances.position_id')
            ->join('projects', 'projects.id', '=', 'reports.project_id')
            ->join('members', 'members.id', '=', 'performances.member_id')
            ->select(
                'members.*',
                'reports.id as report_id',
                'performances.*',
                DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                'results.*',
                'projects.*',
                'projects.name as project',
                'positions.name as position'
            )
            ->whereNull('reports.deleted_at')
            ->whereNull('performances.deleted_at')
            ->where('reports.date_start', 'like', '%'. $request->userInput .'%')
            ->orWhere('reports.date_end', 'like', '%'. $request->userInput .'%')
            ->orWhere('projects.name', 'like', '%'. $request->userInput .'%')
            ->groupBy('reports.id')
            ->orderBy('reports.date_start', 'desc')
            ->get();



        foreach ($reports as $key => $value) {
            $query = DB::table('reports')
                ->join('performances', 'performances.report_id', '=', 'reports.id')
                ->join('results', 'results.performance_id', '=', 'performances.id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->join('projects', 'projects.id', '=', 'reports.project_id')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->select(
                    'reports.id as report_id',
                    'members.*',
                    'performances.*',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                    'results.*',
                    'projects.*',
                    'projects.name as project',
                    'positions.name as position'
                )
                ->whereNull('reports.deleted_at')
                ->whereNull('performances.deleted_at')
                ->where('performances.report_id', $value->report_id)
                ->where('results.report_id', $value->report_id)
                ->groupBy('performances.id')
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->get();

            array_push($report_array, $query);
        }

        return response()->json($report_array);
    }
    public function paginateDetails()
    {
        return Report::orderBy('created_at', 'desc')->paginate(4);
    }
    public function paginate()
    {
        $report = Report::orderBy('created_at', 'desc')->paginate(4);
        $report_array = array();

        // will fetch every performance and results for the specific report
        foreach ($report as $key => $value) {
            $query = DB::table('reports')
                ->join('performances', 'performances.report_id', '=', 'reports.id')
                ->join('results', 'results.performance_id', '=', 'performances.id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->join('projects', 'projects.id', '=', 'reports.project_id')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->select(
                    'members.*',
                    'performances.*',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                    'results.*',
                    'projects.*',
                    'projects.name as project',
                    'positions.name as position'
                )
                ->whereNull('reports.deleted_at')
                ->whereNull('performances.deleted_at')
                ->where('performances.report_id', $value->id)
                ->where('results.report_id', $value->id)
                ->groupBy('performances.id')
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->get();

            foreach ($query as $queryKey => $queryValue) {
                $quality_target = DB::table('targets')
                    ->join('positions', 'positions.id', '=', 'targets.position_id')
                    ->join('members', 'members.experience', '=', 'targets.experience')
                    ->select('*')
                    ->where('targets.position_id', $queryValue->position_id)
                    ->where('targets.experience', $queryValue->experience)
                    ->where('targets.type', 'Quality')
                    ->first();

                    $queryValue->quota = (($queryValue->productivity >= 100) && ($queryValue->quality >= $quality_target->value)) ? 'Met' : 'Not met';
            }

                // push each results to custom array
                array_push($report_array, $query);
        }
        return response()->json($report_array);
    }

    public function paginateDepartmentDetails($departmentID)
    {
        return Report::where('department_id', $departmentID)->orderBy('created_at', 'desc')->paginate(4);
    }
    public function paginateDepartment($departmentID)
    {
        $report = Report::where('department_id', $departmentID)->orderBy('created_at', 'desc')->paginate(4);
        $report_array = array();

        // will fetch every performance and results for the specific report
        foreach ($report as $key => $value) {
            $query = DB::table('reports')
                ->join('performances', 'performances.report_id', '=', 'reports.id')
                ->join('results', 'results.performance_id', '=', 'performances.id')
                ->join('positions', 'positions.id', '=', 'performances.position_id')
                ->join('projects', 'projects.id', '=', 'reports.project_id')
                ->join('members', 'members.id', '=', 'performances.member_id')
                ->select(
                    'members.*',
                    'performances.*',
                    DB::raw('DATE_FORMAT(performances.date_start, "%b. %d, %Y") as date_start'),
                    DB::raw('DATE_FORMAT(performances.date_end, "%b. %d, %Y") as date_end'),
                    'results.*',
                    'projects.*',
                    'projects.name as project',
                    'positions.name as position'
                )
                ->whereNull('reports.deleted_at')
                ->whereNull('performances.deleted_at')
                ->where('performances.report_id', $value->id)
                ->where('results.report_id', $value->id)
                ->groupBy('performances.id')
                ->orderBy('positions.name')
                ->orderBy('members.full_name')
                ->get();

                foreach ($query as $queryKey => $queryValue) {
                    $quality_target = DB::table('targets')
                        ->join('positions', 'positions.id', '=', 'targets.position_id')
                        ->join('members', 'members.experience', '=', 'targets.experience')
                        ->select('*')
                        ->where('targets.position_id', $queryValue->position_id)
                        ->where('targets.experience', $queryValue->experience)
                        ->where('targets.type', 'Quality')
                        ->first();

                        $queryValue->quota = (($queryValue->productivity >= 100) && ($queryValue->quality >= $quality_target->value)) ? 'Met' : 'Not met';
                }

                // push each results to custom array

                array_push($report_array, $query);
        }
        return response()->json($report_array);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Report::where('id', $id)->delete();

        Performance::where('report_id', $id)->delete();
        Result::where('report_id', $id)->delete();
    }
}
