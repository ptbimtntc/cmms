<?php

namespace App\Http\Controllers;

use App\Exports\GreasingScheduleTemplateExport;
use App\Exports\MachineChecklistTemplateExport;
use App\Exports\MachineMeasurementTemplateExport;
use App\Exports\MachineProblemFindingTemplateExport;
use App\Exports\MachineProblemTemplateExport;
use App\Exports\MachineTemplateExport;
use App\Exports\PMScheduleTemplateExport;
use App\Exports\PredictiveProblemFindingTemplateExport;
use App\Exports\PredictiveWorkOrderTemplateExport;
use App\Exports\SmallPmRowTemplateExport;
use App\Exports\SmallPmScheduleTemplateExport;
use App\Exports\SparepartInjectionTemplateExport;
use App\Exports\SparepartTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class ImportTemplateController extends Controller
{
    public function index()
    {
        return view('import-templates.index');
    }

    public function download($type)
    {

        switch ($type) {

            case 'machine':

                return Excel::download(
                    new MachineTemplateExport,
                    'machine_import_template.xlsx'
                );

            case 'sparepart':

                return Excel::download(
                    new SparepartTemplateExport,
                    'sparepart_import_template.xlsx'
                );

            case 'sparepart-injection':

                return Excel::download(
                    new SparepartInjectionTemplateExport,
                    'sparepart_injection_import_template.xlsx'
                );

            case 'pm-schedule':

                return Excel::download(
                    new PMScheduleTemplateExport,
                    'pm_schedule_import_template.xlsx'
                );

            case 'machine-problem':

                return Excel::download(
                    new MachineProblemTemplateExport,
                    'machine_problem_import_template.xlsx'
                );

            case 'machine-problem-finding':

                return Excel::download(
                    new MachineProblemFindingTemplateExport,
                    'machine_problem_finding_import_template.xlsx'
                );

            case 'machine-measurement':

                return Excel::download(
                    new MachineMeasurementTemplateExport,
                    'machine_measurement_import_template.xlsx'
                );

            case 'machine-checklist':

                return Excel::download(
                    new MachineChecklistTemplateExport,
                    'machine_checklist_import_template.xlsx'
                );

            case 'greasing-schedule':

                return Excel::download(
                    new GreasingScheduleTemplateExport,
                    'greasing_schedule_import_template.xlsx'
                );

            case 'small-pm-row':

                return Excel::download(
                    new SmallPmRowTemplateExport,
                    'small_pm_row_import_template.xlsx'
                );

            case 'small-pm-schedule':

                return Excel::download(
                    new SmallPmScheduleTemplateExport,
                    'small_pm_schedule_import_template.xlsx'
                );

            case 'predictive-work-order':

                return Excel::download(
                    new PredictiveWorkOrderTemplateExport,
                    'predictive_work_order_import_template.xlsx'
                );

            case 'predictive-problem-finding':

                return Excel::download(
                    new PredictiveProblemFindingTemplateExport,
                    'predictive_problem_finding_import_template.xlsx'
                );

            default:
                abort(404);
        }
    }
}
