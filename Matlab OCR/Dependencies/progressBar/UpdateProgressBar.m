function UpdateProgressBar(string,FractionProgress,ROW)
%% Just a little helper function to update the Progess Bar 
% 7/29/15

hPROG     = findobj('tag','ProgressBar');
if isempty(hPROG), return, end

figure(hPROG)
TotalRows = length(get(hPROG,'child')); % total rows in Progress Bar
RowValues = [ones(1,ROW-1) FractionProgress 0*[(ROW+1):TotalRows]];

RowValues(RowValues==0) = eps; % bug fix; prevent erasing row labels

RowValues = num2cell(RowValues);


progressbar(RowValues{:})
if ~ishandle(hPROG), return, end    % if deleted, then quit

PercentComplete = sprintf('%1d%%', floor(100*FractionProgress));
if length(PercentComplete)<4, PercentComplete(end+1:4) = ' '; end

set(hPROG,'Name',[PercentComplete,' ',string]);
drawnow