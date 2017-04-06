%% Supervised training for templates
function TemplateTraining(varargin)
    % Direct callbacks to appropriate functions
    if nargin==0 || (nargin==1 && strcmp(varargin{1}, 'browse'))
        delete(findobj('tag','TrainingFigure'));
        hFIG = openfig('TemplateTraining.fig');
        
        % Load and store data
        T = LoadTemplateData( nargin==1 );
        if isempty(T), return; end
        guidata(hFIG,T);
        %ResetTrainedData;
        
        % Plot initial data
        NextCharacter(hFIG);
        set(hFIG,'vis','on');
    elseif nargin==1 && strcmp(varargin{1}, 'reset')
        ResetTrainedData;
        TemplateTraining;
    elseif nargin==1 && strcmp(varargin{1}, 'changenumber')
        ChangeNumEdges;
    elseif nargin==1 && strcmp(varargin{1}, 'callbacks')
        action = get(gcbo,'tag');
        callbacks(action);
    elseif nargin > 1
        action = get(gcbo,'tag');
        callbacks(action, varargin{1});
    end
end

%% Custom Callback function
function callbacks(varargin)
    action = varargin{1};
    switch action
        case 'train_submit'
            SaveCharacter(gcbf);
        case 'train_skip'
            NextCharacter(gcbf);
        case 'train_value'
            TrainingKeyboardSubmit(gcbf, varargin{2});
        case 'train_browse'
            TemplateTraining('browse');
    end
end

%% Initialize Template data set
function T = LoadTemplateData (isBrowse)
    % Load image
    if isBrowse, IMG = LoadImage(true);
    else IMG = LoadImage('Template'); end
    if isempty(IMG), T=''; return; end
    
    %IMG = LoadImage('Template');
    EDGES = getfield( CannyEdgeDetection(IMG), 'edgesLIST' );
    COMBINED = CombineCharacters(EDGES);
    LIMITED = LimitEdges(COMBINED);
    
    T.INDEX = 0;
    T.EDGES = COMBINED;
    T.LIMITED = LIMITED;
    %T.TRAINED = cell(0);
end

%% Display next character to train
function NextCharacter(hFIG)
    % Load and clear plot
    handles = guihandles(hFIG);
    hPLOT   = handles.char_plot;
    hVALUE  = handles.train_value;
    axes(hPLOT);
    hold on;
    cla;
    
    % Graph next character
    T = guidata(hFIG);
    T.INDEX = T.INDEX + 1;
    if T.INDEX > length(T.EDGES), return, end
    POINTS = T.EDGES{T.INDEX};
    scatter(POINTS(:,1), POINTS(:,2),'.');
    grid off;
    axis ij;
    guidata(hFIG, T);
    set(hFIG,'Visible','on');
    set(hPLOT,'Visible','on');
    set(hVALUE, 'String', '');
    set(hVALUE, 'KeyPressFcn', @TemplateTraining);
end

%% Train templates with sample
function SaveCharacter (hFIG)
    % Retrieve entered value
    handles = guihandles(hFIG);
    hVALUE   = handles.train_value;
    VALUE = get(hVALUE, 'String');
    if isempty(VALUE), return; end
    
    % Load data
    T = guidata(hFIG);
    DEFAULT = OcrDefaults;
    if exist(DEFAULT.TrainedData, 'file') == 2
        load(DEFAULT.TrainedData, 'TRAINED');
    else
        TRAINED = {};
    end
    
    % Create structure with character information
    newSample.VALUE = VALUE;
    newSample.EDGES = T.EDGES{T.INDEX};
    newSample.LIMITED = T.LIMITED(T.INDEX,:,:);
    newSample.SHAPE = ComputeShapeContext( squeeze(newSample.LIMITED) );
    %newSample.SHAPE = ComputeShapeContext( unique(squeeze(newSample.LIMITED),'rows') );
    
    % Save new data
    TRAINED{ length(TRAINED)+1 } = newSample;
    save(DEFAULT.TrainedData, 'TRAINED');
    
    % Display next character
    NextCharacter(gcbf);
end

%% Change Number of Limited Edges
function ChangeNumEdges
    DEFAULT = OcrDefaults;
    assert(exist(DEFAULT.TrainedData, 'file') == 2);
    load(DEFAULT.TrainedData, 'TRAINED');
    for t = 1:length(TRAINED)
        TRAINED{t}.LIMITED = LimitEdges({ TRAINED{t}.EDGES });
        TRAINED{t}.SHAPE   = ComputeShapeContext( squeeze(TRAINED{t}.LIMITED) );
    end
    save(DEFAULT.TrainedData, 'TRAINED');
end

%% Reset Trained Data file
function ResetTrainedData
    DEFAULT = OcrDefaults;
    TRAINED = cell(0);
    save(DEFAULT.TrainedData, 'TRAINED');
end

%% 
function TrainingKeyboardSubmit (hFIG, evt)
    %evt.Key;
    %handles = guihandles(hFIG);
    %hVALUE   = handles.train_value;
end