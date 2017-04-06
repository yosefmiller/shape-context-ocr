%% Display grayscale
function out = gray2uint (matrix)
    [h, w] = size(matrix);
    newIMG = zeros(h, w, 3);
    for y = 1:h
        for x = 1:w
            for rgb = 1:3
                newIMG(y, x, rgb) = matrix(y, x);
            end
        end
    end
    out = uint8(newIMG);
end