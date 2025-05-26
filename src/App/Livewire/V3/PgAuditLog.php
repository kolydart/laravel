<?php

namespace Kolydart\Laravel\App\Livewire\V3;

use App\AuditLog;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Footer;
use PowerComponents\LivewirePowerGrid\Header;
use PowerComponents\LivewirePowerGrid\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
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

        if (auth()?->user()?->roles()?->pluck('id')->contains(1) ?? false) {
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
            ->groupBy(['subject_id', 'subject_type', 'user_id', 'host', 'created_at', 'description'])
            ;

        /**
         * livewire component parameters (related to a model)
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

                    // if media has model_type and model_id, link to model
                    if(isset($media->custom_properties['model_type']) && isset($media->custom_properties['model_id'])){
                        $model_type = $media->custom_properties['model_type'];
                        $model_id = $media->custom_properties['model_id'];
                        $route = 'admin.'.strtolower(class_basename($model_type)).'s.show';

                        if(route_exists($route)){
                            return '<a href="'.route($route, $model_id).'">'.$model->subject_id.'</a>';
                        }
                    }

                    // if media has properties.model_type and properties.model_id, link to model
                    if(isset($model->properties['model_type']) && isset($model->properties['model_id'])){
                        $model_type = $model->properties['model_type'];
                        $model_id = $model->properties['model_id'];
                        $route = 'admin.'.strtolower(class_basename($model_type)).'s.show';

                        if(route_exists($route)){
                            return '<a href="'.route($route, $model_id).'">'.$model->subject_id.'</a>';
                        }
                    }
                }

                return $model->subject_id;
            })
            ->add('subject_type', function($model){
                return class_basename($model->subject_type);
            })
            ->add('user_name', function($model){
                if($model->user_id){
                    $user = User::find($model->user_id);
                    return $user->name ?? $model->user_id;
                }
                return 'System';
            })
            ->add('properties_excerpt', function($model){
                if(is_array($model->properties)){
                    return Str::limit(json_encode($model->properties), 50);
                }
                return Str::limit($model->properties, 50);
            })
            ->add('created_at_formatted', function($model){
                return $model->created_at->format('Y-m-d H:i:s');
            })
            ->add('host');
    }

    /*
    |--------------------------------------------------------------------------
    |  Include Columns
    |--------------------------------------------------------------------------
    | Include the columns added columns, making them visible on the Table.
    | Each column can be configured with properties, filters, actions...
    |
    */
    public function columns(): array
    {
        return [
            Column::add()
                ->title('ID')
                ->field('id', 'audit_logs.id')
                ->searchable()
                ->sortable(),

            Column::add()
                ->title(trans('cruds.user.title_singular'))
                ->field('user_name','users.name')
                ->sortable()
                ->searchable(),

            Column::add()
                ->title(trans('cruds.auditLog.fields.description'))
                ->field('description')
                ->sortable()
                ->searchable(),

            Column::add()
                ->title(trans('cruds.auditLog.fields.subject_type'))
                ->field('subject_type')
                ->sortable()
                ->searchable(),

            Column::add()
                ->title(trans('cruds.auditLog.fields.subject_id'))
                ->field('subject_id')
                ->sortable()
                ->searchable(),

            Column::add()
                ->title(trans('cruds.auditLog.fields.properties'))
                ->field('properties_excerpt','properties')
                ->sortable()
                ->searchable(),

            Column::add()
                ->title(trans('cruds.auditLog.fields.created_at'))
                ->field('created_at','audit_logs.created_at')
                ->searchable()
                ->sortable(),

            Column::add()
                ->title(trans('cruds.auditLog.fields.host'))
                ->field('host')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
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
                ->optionLabel('name'),

            Filter::multiSelect('subject_type','subject_type')
                ->dataSource(AuditLog::selectRaw('subject_type, SUBSTRING_INDEX(subject_type, "\\\", -1) AS subject_clean')
                    ->distinct()
                    ->orderBy('subject_type')
                    ->get()
                )
                ->optionLabel('subject_clean')
                ->optionValue('subject_type'),

            Filter::multiSelect('description','description')
                ->dataSource(AuditLog::query()
                    ->orderBy('description')
                    ->select('description')
                    ->orderBy('description')
                    ->distinct()
                    ->get()
                )
                ->optionLabel('description')
                ->optionValue('description'),

            Filter::inputText('subject_id','subject_id')
                ->operators(['contains','is']),

            Filter::inputText('properties','properties')
                ->operators(['contains']),

            Filter::datepicker('created_at','audit_logs.created_at'),

            Filter::inputText('host','host')
                ->operators(['contains']),
        ];
    }

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