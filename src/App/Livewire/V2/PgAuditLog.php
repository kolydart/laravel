<?php

namespace Kolydart\Laravel\App\Livewire\V2;

use App\AuditLog;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PowerComponents\LivewirePowerGrid\Rules\{Rule, RuleActions};
use PowerComponents\LivewirePowerGrid\Traits\ActionButton;
use PowerComponents\LivewirePowerGrid\{Button, Column, Exportable, Footer, Header, PowerGrid, PowerGridComponent, PowerGridEloquent};
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use gateweb\common\Presenter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class PgAuditLog extends PowerGridComponent
{
    use WithExport;

    // passed model
    public $model = null;

    public string $primaryKey = 'audit_logs.id';
    public string $sortField = 'audit_logs.created_at';
    public string $sortDirection = 'desc';


    /*
    |--------------------------------------------------------------------------
    |  Features Setup
    |--------------------------------------------------------------------------
    | Setup Table's general features
    |
    */
    public function setUp(): array
    {
        $this->showCheckBox();

        $this->showFilters = true;

        if (\App::environment() == 'production') {
            $this->persist(['columns']);
        }

        $arr = [];

        if (auth()->user()->roles()->get()->contains('id',1)) {

            $arr[] = Exportable::make('export')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV);

        }

        $arr[] = Header::make()
            ->showToggleColumns()
            ->showSearchInput();

        $arr[] = Footer::make()
            ->showPerPage()
            ->showRecordCount();

        return $arr;

    }

    /*
    |--------------------------------------------------------------------------
    |  Datasource
    |--------------------------------------------------------------------------
    | Provides data to your Table using a Model or Collection
    |
    */

    /**
    * PowerGrid datasource.
    *
    * @return Builder<\App\AuditLog>
    */
    public function datasource(): Builder
    {
        $query = AuditLog::query()
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->selectRaw("audit_logs.*")
            // ->with([
            //     'user',
            // ])
            // solution for eloquent creating duplicate records (new records not instantly available for retrieval)
            ->groupBy(['subject_id', 'subject_type', 'user_id', 'host', 'created_at', 'description'])
            ;

        /**
         * livewire component parameters (related to a model)
         * @use
         * <livewire:pg-audit-log :model="$model" lazy />
         */
        if($this->model){

            $model = $this->model;
            $fk = Str::of($model->getTable())->singular()."_id";

            $query->where(function($query) use ($model,$fk){

                // current model
                $query->where(function($query) use ($model){
                    $query
                    ->where('audit_logs.subject_type',$model::class)
                    ->where('audit_logs.subject_id',$model->id);
                })

                // or model as foreign key
                ->orWhere(function($query) use ($model,$fk){
                    $query
                    ->whereRaw('JSON_VALID(properties)')
                    ->where('audit_logs.properties->'.$fk, $model->id);
                })

                // or model in Media
                ->orWhere(function($query) use($model){
                    $query->where('audit_logs.subject_type', Media::class)
                        ->where('audit_logs.properties->model_type',$model::class)
                        ->where('audit_logs.properties->model_id',$model->id);
                    });

            });

        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    |  Relationship Search
    |--------------------------------------------------------------------------
    | Configure here relationships to be used by the Search and Table Filters.
    |
    */

    /**
     * Relationship search.
     *
     * @return array<string, array<int, string>>
     */
    public function relationSearch(): array
    {
        return ['user' => ['name']];
    }

    /*
    |--------------------------------------------------------------------------
    |  Add Column
    |--------------------------------------------------------------------------
    | Make Datasource fields available to be used as columns.
    | You can pass a closure to transform/modify the data.
    |
    */
    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('audit_logs.id')
            ->add('description')
            ->add('subject_id', function($model){

                if( $model->subject_type == Media::class ){

                    $media = Media::find($model->subject_id);

                    // defence; if $media does not exist, just return id
                    if(!$media){

                        return $model->subject_id;

                    }

                    return '<a href="'
                        .route('admin.'.str(str($media->model_type)->explode('\\')->last())->kebab()->plural().'.show',$media->model_id)
                    .'" target="_blank">'.$model->subject_id.'</a>';

                }elseif(Route::has('admin.'.str(str($model->subject_type)->explode('\\')->last())->kebab()->plural().'.show')){

                    return '<a href="'
                        .route('admin.'.str(str($model->subject_type)->explode('\\')->last())->kebab()->plural().'.show',$model->subject_id)
                    .'" target="_blank">'.$model->subject_id.'</a>';

                }else{

                    return $model->subject_id;

                }

            })

            ->add('subject_type', fn($model) => str($model->subject_type)->explode('\\')->last() ?? '' )
            ->add('user_id', fn ($model) => $model->user_id)
            ->add('user_name', fn($model) => $model->user->name ?? '' )
            ->add('properties')
            ->add('properties_excerpt', fn ($model) => Presenter::left(rawurldecode(http_build_query($model->properties->toArray())),30))
            ->add('host')
            ->add('created_at_formatted', fn (AuditLog $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'))
            ->add('updated_at_formatted', fn (AuditLog $model) => Carbon::parse($model->updated_at)->format('d/m/Y H:i:s'));
    }

    /*
    |--------------------------------------------------------------------------
    |  Include Columns
    |--------------------------------------------------------------------------
    | Include the columns added columns, making them visible on the Table.
    | Each column can be configured with properties, filters, actions...
    |
    */

     /**
     * PowerGrid Columns.
     *
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            /*
            Column::add()
                ->title('Id')
                ->field('id','audit_logs.id')
                ->sortable()
                ->makeInputRange(),
            */

            Column::add()
                ->title(trans('cruds.user.title_singular'))
                ->field('user_name','users.name')
                ->sortable()
                ->searchable()
                ,

            Column::add()
                ->title(trans('cruds.auditLog.fields.description'))
                ->field('description')
                ->sortable()
                ->searchable()
                ,

            Column::add()
                ->title(trans('cruds.auditLog.fields.subject_type'))
                ->field('subject_type')
                ->sortable()
                ->searchable()
                ,

            Column::add()
                ->title(trans('cruds.auditLog.fields.subject_id'))
                ->field('subject_id')
                ->sortable()
                ->searchable()
                ,

            Column::add()
                ->title(trans('cruds.auditLog.fields.properties'))
                ->field('properties_excerpt','properties')
                ->sortable()
                ->searchable()
                ,

            Column::add()
                ->title(trans('cruds.auditLog.fields.created_at'))
                ->field('created_at','audit_logs.created_at')
                ->searchable()
                ->sortable()
                ,

            Column::add()
                ->title(trans('cruds.auditLog.fields.host'))
                ->field('host')
                ->sortable()
                ->searchable()
                ,

            Column::action('Action'),

        ]
        ;
    }


    /**
     * PowerGrid Filters.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [

            Filter::multiSelect('user_name', 'user_id')
                ->dataSource(
                    User::whereIn('id',AuditLog::whereNotNull('user_id')->distinct()->pluck('user_id')->toArray())
                        ->select("id","name")
                        ->orderBy('name')
                        ->get()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ,

            /**
            Filter::inputText('user_name')
                ->operators(['contains'])
                ->builder(function (Builder $query, mixed $value) {
                    return $query
                        ->where('name', 'like', "%".$value['value']."%")
                        ;
                })
                ,
             */

            /** @use Filter::select() if slim-select is not installed */
            Filter::multiSelect('subject_type','subject_type')
                ->dataSource(AuditLog::selectRaw('subject_type, SUBSTRING_INDEX(subject_type, "\\\", -1) AS subject_clean')
                    ->distinct()
                    ->orderBy('subject_type')
                    ->get()
                    ->reject(function($row){
                        return auth()->user()->roles()->get()->doesntContain('id',1)
                            && Gate::denies(str($row->subject_clean)->lower()."_show");
                    })
                )
                ->optionLabel('subject_clean')
                ->optionValue('subject_type')

                ,

            /**
            Filter::inputText('subject_type', 'subject_type')
                ->operators(['contains'])
                ,
             */


            /** @use Filter::select() if slim-select is not installed */
            Filter::multiSelect('description','description')
                ->dataSource(AuditLog::query()
                    ->when(
                        auth()->user()->roles()->get()->doesntContain('id',1),
                        fn ($query) => $query->whereIn('description',['create','update','delete'])
                    )
                    ->orderBy('description')
                    ->select('description')
                    ->orderBy('description')
                    ->distinct()
                    ->get()
                )
                ->optionLabel('description')
                ->optionValue('description')
                ,

            Filter::inputText('subject_id','subject_id')
                ->operators(['contains','is'])
                ,

            Filter::inputText('properties','properties')
                ->operators(['contains'])
                ,

            Filter::datepicker('created_at','audit_logs.created_at')
                ,

            Filter::inputText('host','host')
                ->operators(['contains'])
                ,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Actions Method
    |--------------------------------------------------------------------------
    | Enable the method below only if the Routes below are defined in your app.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Actions Method
    |--------------------------------------------------------------------------
    | Enable the method below only if the Routes below are defined in your app.
    |
    */

     /**
     * PowerGrid AuditLog Action Buttons.
     *
     * @return array<int, Button>
     */

    public function actions(): array
    {
       return [
            Button::add('view')
                ->can(\Gate::allows('audit_log_show'))
                ->render(function (AuditLog $model) {
                    $slot = trans('global.view');
                    $url  = route('admin.audit-logs.show',$model);
                    return <<<HTML
                        <a href="$url" id="action_view_$model->id" class="btn btn-xs btn-primary" target="_blank">$slot</a>
                    HTML;
                }),

        ];
    }
} 