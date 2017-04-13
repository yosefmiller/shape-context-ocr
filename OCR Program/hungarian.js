// Hungarian Algorithm
// Kyle Krafka
// April 24, 2013

// Original implementation (Java):
// http://konstantinosnedas.com/dev/soft/munkres.htm (Konstantinos A. Nedas)

// Store everything in an object, which is created from the
// following function, which executes and returns immediately.
// This is the module pattern, and allows us to make some methods
// private.  The argument "global" will take on the global scope,
// and because the second argument is not passed in, the "undefined"
// variable will have the value of undefined (as a safety precaution).
(function (global, undefined) {
    // Expose just the hgAlgorithm method
    // Usage: hungarian(matrix, [isProfitMatrix=false], [returnSum=false])
    global.hungarian = hgAlgorithm;

    // isProfitMatrix is optional, but if it exists and is the value
    // true, the costs will be treated as profits
    // returnSum is also optional, but will
    // sum up the chosen costs/profits and return that instead
    // of the assignment matrix.
    function hgAlgorithm(matrix, isProfitMatrix, returnSum) {
        var cost, i, j,
        mask = [],			// the mask array: [matrix.length] x [matrix[0].length]
            rowCover = [],	// the row covering vector: [matrix.length]
            colCover = [],	// the column covering vector: [matrix[0].length]
            zero_RC = [0, 0],	// position of last zero from Step 4: [2]
            path = [],		// [matrix.length * matrix[0].length + 2] x [2]
            step = 1,
            done = false,
            maxWeightPlusOne,	// Should be larger or smaller than all matrix values.
            // Number.MAX_VALUE causes overflow on profits
            assignments = [],	// [min(matrix.length, matrix[0].length)] x [2]
            assignmentsSeen;

        // Create the cost matrix, so we can work without modifying the original input.
        cost = copyOf(matrix);
		
        maxWeightPlusOne = findLargest(cost) + 1;
		
        // If it's a rectangular matrix, pad it with a forbidden value (MAX_VALUE).
        // Whether they are chosen first or last (profit or cost, respectively)
        // shouldn't matter, as we will not include assignments out of range anyway.
        makeSquare(cost, maxWeightPlusOne);

        if (isProfitMatrix === true) {
            for (i = 0; i < cost.length; i++) {
                for (j = 0; j < cost[i].length; j++) {
                    cost[i][j] = maxWeightPlusOne - cost[i][j];
                }
            }
        }

        // Initialize the 1D arrays with zeros
        for (i = 0; i < cost.length; i++)	{ rowCover[i] = 0; }
        for (j = 0; j < cost[0].length; j++){ colCover[j] = 0; }

        // Initialize the inside arrays to make 2D arrays
        // Fill with zeros
        for (i = 0; i < cost.length; i++) {
            mask[i] = [];
            for (j = 0; j < cost[0].length; j++) {
                mask[i][j] = 0;
            }
        }
        for (i = 0; i < Math.min(matrix.length, matrix[0].length); i++)	{ assignments[i] = [0, 0]; }
        for (i = 0; i < (cost.length * cost[0].length + 2); i++)		{ path[i] = []; }

        // Matrix execution loop
        while (!done) {
            switch (step) {
                case 1:
                    step = hg_step1(step, cost);
                    break;
                case 2:
                    step = hg_step2(step, cost, mask, rowCover, colCover);
                    break;
                case 3:
                    step = hg_step3(step, mask, colCover);
                    break;
                case 4:
                    step = hg_step4(step, cost, mask, rowCover, colCover, zero_RC);
                    break;
                case 5:
                    step = hg_step5(step, mask, rowCover, colCover, zero_RC, path);
                    break;
                case 6:
                    step = hg_step6(step, cost, rowCover, colCover);
                    break;
                case 7:
                    done = true;
                    break;
            }
        }

        // In an input matrix taller than it is wide, the first assignment
        // column will have to skip some numbers, so the index will not
        // always match the first column.
        assignmentsSeen = 0;
        for (i = 0; i < mask.length; i++) {
            for (j = 0; j < mask[i].length; j++) {
                if (i < matrix.length && j < matrix[0].length && mask[i][j] === 1) {
                    assignments[assignmentsSeen][0] = i;
                    assignments[assignmentsSeen][1] = j;
                    assignmentsSeen++;
                }
            }
        }

        if (returnSum === true) {
            // If you want to return the min or max sum instead of the assignment
            // array, set the returnSum argument (or use this
            // code on the return value outside of this function):
            var sum = 0;
            for (i = 0; i < assignments.length; i++) {
                sum = sum + matrix[assignments[i][0]][assignments[i][1]];
            }
            return sum;
        } else {
            return assignments;
        }
    }

    function hg_step1(step, cost) {
        // For each row of the cost matrix, find the smallest element and
        // subtract it from every other element in its row.

        var minVal, i, j;

        for (i = 0; i < cost.length; i++) {
            minVal = cost[i][0];
            for (j = 0; j < cost[i].length; j++) {
                if (minVal > cost[i][j]) {
                    minVal = cost[i][j];
                }
            }
            for (j = 0; j < cost[i].length; j++) {
                cost[i][j] -= minVal;
            }
        }

        step = 2;
        return step;
    }

    function hg_step2(step, cost, mask, rowCover, colCover) {
        // Marks uncovered zeros as starred and covers their row and column.

        var i, j;

        for (i = 0; i < cost.length; i++) {
            for (j = 0; j < cost[i].length; j++) {
                if (cost[i][j] === 0 && colCover[j] === 0 && rowCover[i] === 0) {
                    mask[i][j] = 1;
                    colCover[j] = 1;
                    rowCover[i] = 1;
                }
            }
        }

        // Reset cover vectors
        clearCovers(rowCover, colCover);

        step = 3;
        return step;
    }

    function hg_step3(step, mask, colCover) {
        // Cover columns of starred zeros.  Check if all columns are covered.

        var i, j, count;

        // Cover columns of starred zeros
        for (i = 0; i < mask.length; i++) {
            for (j = 0; j < mask[i].length; j++) {
                if (mask[i][j] === 1) {
                    colCover[j] = 1;
                }
            }
        }

        // Check if all columns are covered
        count = 0;
        for (j = 0; j < colCover.length; j++) {
            count += colCover[j];
        }

        // Should be cost.length, but okay, because mask has same dimensions
        if (count >= mask.length) {
            step = 7;
        } else {
            step = 4;
        }

        return step;
    }

    function hg_step4(step, cost, mask, rowCover, colCover, zero_RC) {
        // Find an uncovered zero in cost and prime it (if none, go to Step 6).
        // Check for star in same row: if yes, cover the row and uncover the
        // star's column.  Repeat until no uncovered zeros are left and go to
        // Step 6.  If not, save location of primed zero and go to Step 5.

        var row_col = [0, 0], // size: 2, holds row and column of uncovered zero
            done = false,
            j, starInRow;

        while (!done) {
            row_col = findUncoveredZero(row_col, cost, rowCover, colCover);
            if (row_col[0] === -1) {
                done = true;
                step = 6;
            } else {
                // Prime the found uncovered zero
                mask[row_col[0]][row_col[1]] = 2;

                starInRow = false;
                for (j = 0; j < mask[row_col[0]].length; j++) {
                    // If there is a star in the same row...
                    if (mask[row_col[0]][j] === 1) {
                        starInRow = true;
                        // Remember its column
                        row_col[1] = j;
                    }
                }

                if (starInRow) {
                    rowCover[row_col[0]] = 1; // Cover the star's row
                    colCover[row_col[1]] = 0; // Uncover its column
                } else {
                    zero_RC[0] = row_col[0]; // Save row of primed zero
                    zero_RC[1] = row_col[1]; // Save column of primed zero
                    done = true;
                    step = 5;
                }
            }
        }

        return step;
    }

    // Auxiliary function for hg_step4
    function findUncoveredZero(row_col, cost, rowCover, colCover) {
        var i, j, done;

        row_col[0] = -1; // Just a check value.  Not a real index.
        row_col[1] = 0;

        i = 0;
        done = false;

        while (!done) {
            j = 0;
            while (j < cost[i].length) {
                if (cost[i][j] === 0 && rowCover[i] === 0 && colCover[j] === 0) {
                    row_col[0] = i;
                    row_col[1] = j;
                    done = true;
                }
                j = j + 1;
            }
            i++;
            if (i >= cost.length) {
                done = true;
            }
        }

        return row_col;
    }

    function hg_step5(step, mask, rowCover, colCover, zero_RC, path) {
        // Construct series of alternating primes and stars.  Start with prime
        // from step 4.  Take star in the same column.  Next, take prime in the
        // same row as the star.  Finish at a prime with no star in its column.
        // Unstar all stars and star the primes of the series.  Erase any other
        // primes.  Reset covers.  Go to Step 3.

        var count, done, r, c;

        count = 0; // Counts rows of the path matrix
        path[count][0] = zero_RC[0]; // Row of last prime
        path[count][1] = zero_RC[1]; // Column of last prime

        done = false;
        while (!done) {
            r = findStarInCol(mask, path[count][1]);
            if (r >= 0) {
                count = count + 1;
                path[count][0] = r; // Row of starred zero
                path[count][1] = path[count - 1][1]; // Column of starred zero
            } else {
                done = true;
            }

            if (!done) {
                c = findPrimeInRow(mask, path[count][0]);
                count = count + 1;
                path[count][0] = path[count - 1][0]; // Row of primed zero
                path[count][1] = c;
            }
        }

        convertPath(mask, path, count);
        clearCovers(rowCover, colCover);
        erasePrimes(mask);

        step = 3;
        return step;
    }

    // Auxiliary function for hg_step5
    function findStarInCol(mask, col) {
        var r, i;

        // Again, this is a check value
        r = -1;
        for (i = 0; i < mask.length; i++) {
            if (mask[i][col] === 1) {
                r = i;
            }
        }

        return r;
    }

    // Auxiliary function for hg_step5
    function findPrimeInRow(mask, row) {
        var c, j;

        c = -1;
        for (j = 0; j < mask[row].length; j++) {
            if (mask[row][j] === 2) {
                c = j;
            }
        }

        return c;
    }

    // Auxiliary function for hg_step5
    function convertPath(mask, path, count) {
        var i;

        for (i = 0; i <= count; i++) {
            if (mask[path[i][0]][path[i][1]] === 1) {
                mask[path[i][0]][path[i][1]] = 0;
            } else {
                mask[path[i][0]][path[i][1]] = 1;
            }
        }
    }

    // Auxiliary function for hg_step5
    function erasePrimes(mask) {
        var i, j;

        for (i = 0; i < mask.length; i++) {
            for (j = 0; j < mask[i].length; j++) {
                if (mask[i][j] === 2) {
                    mask[i][j] = 0;
                }
            }
        }
    }

    // Auxiliary function for hg_step5 (and others)
    function clearCovers(rowCover, colCover) {
        var i, j;

        for (i = 0; i < rowCover.length; i++) {
            rowCover[i] = 0;
        }
        for (j = 0; j < colCover.length; j++) {
            colCover[j] = 0;
        }
    }

    function hg_step6(step, cost, rowCover, colCover) {
        // Find smallest uncovered value in cost: a.) Add it to every element of
        // uncovered rows, b.) Subtract it from every element of uncovered
        // columns.  Go to Step 4.

        var minVal, i, j;

        minVal = findSmallest(cost, rowCover, colCover);

        for (i = 0; i < rowCover.length; i++) {
            for (j = 0; j < colCover.length; j++) {
                if (rowCover[i] === 1) {
                    cost[i][j] += minVal;
                }
                if (colCover[j] === 0) {
                    cost[i][j] -= minVal;
                }
            }
        }

        step = 4;
        return step;
    }

    // Auxiliary function for hg_step6
    function findSmallest(cost, rowCover, colCover) {
        var minVal, i, j;

        // There cannot be a larger cost than this
        minVal = Number.MAX_VALUE;
        // Now, find the smallest uncovered value
        for (i = 0; i < cost.length; i++) {
            for (j = 0; j < cost[i].length; j++) {
                if (rowCover[i] === 0 && colCover[j] === 0 && minVal > cost[i][j]) {
                    minVal = cost[i][j];
                }
            }
        }

        return minVal;
    }

    // Takes in a 2D array and finds the largest element
    // This is used in the Hungarian algorithm if the user chooses "max"
    // (indicating their matrix values represent profit) so that cost values
    // are subtracted from the largest value.
    function findLargest(matrix) {
        var i, j, largest = Number.MIN_VALUE;
        for (i = 0; i < matrix.length; i++) {
            for (j = 0; j < matrix[i].length; j++) {
                if (matrix[i][j] > largest) {
                    largest = matrix[i][j];
                }
            }
        }
        return largest;
    }

    // Copies all elements of a 2D array to a new array
    function copyOf(original) {
        var i, j,
        copy = [];

        for (i = 0; i < original.length; i++) {
            copy[i] = [];
            for (j = 0; j < original[i].length; j++) {
                copy[i][j] = original[i][j];
            }
        }

        return copy;
    }

    // Makes a rectangular matrix square by padding it with some value
    // This modifies the matrix argument directly instead of returning a copy
    function makeSquare(matrix, padValue) {
        var rows = matrix.length,
            cols = matrix[0].length,
            i, j;

        if (rows === cols) {
            // The matrix is already square.
            return;
        } else if (rows > cols) {
            // Pad on some extra columns on the right.
            for (i = 0; i < rows; i++) {
                for (j = cols; j < rows; j++) {
                    matrix[i][j] = padValue;
                }
            }
        } else if (rows < cols) {
            // Pad on some extra rows at the bottom.
            for (i = rows; i < cols; i++) {
                matrix[i] = [];
                for (j = 0; j < cols; j++) {
                    matrix[i][j] = padValue;
                }
            }
        }
        // None of the above cases may execute if there is a problem
        // with the input matrix.
    }

})(this);