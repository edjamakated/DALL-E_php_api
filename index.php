<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-5">
        <h1 class="text-2xl font-bold mb-5">Image Generator</h1>
        <input type="text" id="textInput" class="bg-white p-2 border rounded w-full" placeholder="Enter text...">
        <button id="submitBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4">Submit</button>
        <div id="progressBarContainer" class="hidden mt-4">
            <div class="w-full h-2 bg-gray-300">
                <div id="progressBar" class="h-2 bg-blue-500" style="width: 0;"></div>
            </div>
        </div>
        <div id="imageContainer" class="mt-5"></div>
        <div id="ajaxError" class="mt-5 text-red-600 hidden">Oops! Something went wrong. Please try again later.</div>
    </div>

    <script>
        function downloadImage(imageUrl, imageName) {
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = imageName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function resetUI() {
            $('#submitBtn').attr('disabled', false);
            $('#progressBarContainer').addClass('hidden');
            $('#progressBar').css('width', '0');
        }

        $('#submitBtn').click(function() {
            const text = $('#textInput').val();
            $(this).attr('disabled', true);
            $('#progressBarContainer').removeClass('hidden');
            $('#ajaxError').addClass('hidden');
            $('#progressBar').animate({
                width: '100%'
            }, 5000);

            $.ajax({
                url: 'api.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    prompt: text
                }),
                success: function(response) {
                    const imageUrl = response.url;
                    $('#imageContainer').html(`<img src="${imageUrl}" alt="${text}" class="border-4 border-gray-300 hover:border-blue-500 cursor-pointer transition-all duration-300">`);
                    resetUI();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('API call failed:', textStatus, errorThrown);
                    $('#ajaxError').removeClass('hidden');
                    resetUI();
                }
            });
        });

        $('#imageContainer').on('click', 'img', function() {
            const imageUrl = $(this).attr('src');
            const imageName = 'generated_image.png';
            downloadImage(imageUrl, imageName);
        });
    </script>
</body>

</html>
